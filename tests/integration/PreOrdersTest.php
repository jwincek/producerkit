<?php
/**
 * Pre-order lifecycle: creation hardening, cancellation rules, status
 * transitions, hooks, and the REST surface.
 */

declare(strict_types=1);

use function Leftfield\PreOrder\Orders\cancel_order_by_token;
use function Leftfield\PreOrder\Orders\create_order;
use function Leftfield\PreOrder\Orders\get_order_by_token;
use function Leftfield\PreOrder\Orders\get_orders;
use function Leftfield\PreOrder\Orders\update_status;

final class PreOrdersTest extends WP_UnitTestCase {

    private int $product;

    public function set_up(): void {
        parent::set_up();
        $this->product = self::factory()->post->create(['post_type' => 'lfuf_product', 'post_status' => 'publish', 'post_title' => 'Kale']);
        update_post_meta($this->product, '_lfuf_unit', 'bunch');
        update_post_meta($this->product, '_lfuf_price', '$4');
    }

    /** Most tests create several orders; lift the per-IP limit for them. */
    private function allow_many_orders(): void {
        add_filter('lfuf_preorder_rate_limit', fn () => 100);
    }

    private function tomorrow(): string {
        return gmdate('Y-m-d', strtotime(current_time('Y-m-d') . ' +1 day'));
    }

    private function valid_order(array $overrides = []): array {
        return array_merge([
            'name'        => 'Pat Customer',
            'pickup_date' => $this->tomorrow(),
            'items'       => [['product_id' => $this->product, 'qty' => 2]],
        ], $overrides);
    }

    public function test_create_merges_duplicate_lines_and_snapshots_product(): void {
        $order = create_order($this->valid_order([
            'items' => [
                ['product_id' => $this->product, 'qty' => 3],
                ['product_id' => $this->product, 'qty' => 2],
            ],
        ]));

        $this->assertNotWPError($order);
        $this->assertCount(1, $order['items']);
        $this->assertSame(5, $order['items'][0]['qty']);
        $this->assertSame('Kale', $order['items'][0]['title']);
        $this->assertSame('bunch', $order['items'][0]['unit']);
        $this->assertGreaterThan(0, $order['id'], 'insert id must survive the rate-limit transient write');
        $this->assertSame(32, strlen($order['token']));
        $this->assertSame(1, did_action('lfuf_preorder_created'));
    }

    public function test_create_rejects_bad_inputs(): void {
        $this->allow_many_orders();

        $cases = [
            'missing name'    => [$this->valid_order(['name' => '']), 'name_required'],
            'unknown product' => [$this->valid_order(['items' => [['product_id' => 999999, 'qty' => 1]]]), 'invalid_product'],
            'no items'        => [$this->valid_order(['items' => []]), 'items_required'],
            'far-future date' => [$this->valid_order(['pickup_date' => '2030-01-01']), 'invalid_pickup_date'],
            'past date'       => [$this->valid_order(['pickup_date' => '2020-01-01']), 'invalid_pickup_date'],
            'bad location'    => [$this->valid_order(['location_id' => 999999]), 'invalid_location'],
        ];

        foreach ($cases as $label => [$input, $expected_code]) {
            $result = create_order($input);
            $this->assertWPError($result, $label);
            $this->assertSame($expected_code, $result->get_error_code(), $label);
        }
    }

    public function test_honeypot_returns_fake_success_without_inserting(): void {
        $result = create_order($this->valid_order(['honeypot' => 'http://spam']));
        $this->assertSame(0, $result['id'], 'bots get a fake success');
        $this->assertSame(0, get_orders()['total'], 'nothing may be stored');
    }

    public function test_rate_limit_blocks_fourth_order(): void {
        for ($i = 1; $i <= 3; $i++) {
            $this->assertNotWPError(create_order($this->valid_order(['name' => "Customer {$i}"])));
        }
        $fourth = create_order($this->valid_order(['name' => 'Customer 4']));
        $this->assertWPError($fourth);
        $this->assertSame('rate_limited', $fourth->get_error_code());
    }

    public function test_customer_cancel_allowed_only_while_pending_or_confirmed(): void {
        $this->allow_many_orders();

        $order = create_order($this->valid_order());
        update_status($order['id'], 'confirmed');
        $this->assertTrue(cancel_order_by_token($order['token']), 'confirmed orders are cancellable');
        $this->assertSame(1, did_action('lfuf_preorder_cancelled'));

        $order2 = create_order($this->valid_order(['name' => 'Second']));
        update_status($order2['id'], 'ready');
        $refused = cancel_order_by_token($order2['token']);
        $this->assertWPError($refused);
        $this->assertSame('not_cancellable', $refused->get_error_code());
    }

    public function test_status_transitions_fire_hook_and_reject_unknown_status(): void {
        $order = create_order($this->valid_order());

        $this->assertTrue(update_status($order['id'], 'confirmed'));
        $this->assertTrue(update_status($order['id'], 'ready'));
        $this->assertSame(2, did_action('lfuf_preorder_status_changed'));
        $this->assertSame('ready', get_order_by_token($order['token'])['status']);

        $bad = update_status($order['id'], 'teleported');
        $this->assertWPError($bad);
        $this->assertSame('invalid_status', $bad->get_error_code());

        $missing = update_status(999999, 'confirmed');
        $this->assertSame('not_found', $missing->get_error_code());
    }

    public function test_rest_create_lookup_and_cancel_by_token(): void {
        $request = new WP_REST_Request('POST', '/lfuf/v1/preorders');
        $request->set_body_params([
            'name'        => 'Rest Customer',
            'pickup_date' => $this->tomorrow(),
            'items'       => [['product_id' => $this->product, 'qty' => 1]],
        ]);
        $created = rest_do_request($request);
        $this->assertSame(201, $created->get_status());
        $token = $created->get_data()['order']['token'];

        $this->assertSame(200, rest_do_request(new WP_REST_Request('GET', "/lfuf/v1/preorders/{$token}"))->get_status());

        $cancelled = rest_do_request(new WP_REST_Request('DELETE', "/lfuf/v1/preorders/{$token}"));
        $this->assertSame(200, $cancelled->get_status());
        $this->assertSame('cancelled', get_order_by_token($token)['status']);
    }

    public function test_rest_staff_routes_refuse_anonymous(): void {
        wp_set_current_user(0);
        $this->assertSame(401, rest_do_request(new WP_REST_Request('GET', '/lfuf/v1/preorders'))->get_status());

        $status = new WP_REST_Request('POST', '/lfuf/v1/preorders/1/status');
        $status->set_body_params(['status' => 'confirmed']);
        $this->assertSame(401, rest_do_request($status)->get_status());
    }

    public function test_rest_staff_routes_allow_editor(): void {
        $order = create_order($this->valid_order());
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        $list = rest_do_request(new WP_REST_Request('GET', '/lfuf/v1/preorders'));
        $this->assertSame(200, $list->get_status());
        $this->assertSame(1, $list->get_data()['total']);

        $status = new WP_REST_Request('POST', "/lfuf/v1/preorders/{$order['id']}/status");
        $status->set_body_params(['status' => 'confirmed']);
        $this->assertSame(200, rest_do_request($status)->get_status());
    }
}
