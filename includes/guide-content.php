<?php
/**
 * Generated from docs/getting-started.tpl.md by bin/make-guide.js.
 * Do not edit by hand — edit the template and re-run the generator.
 *
 * The {{tokens}} are left unresolved on purpose. ProducerKit\Guide\render()
 * substitutes them against the trade the site actually chose, which is the
 * whole reason this is generated rather than written as a static page.
 *
 * @package ProducerKit
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

return [
	'source_hash' => '1896570dabdbcc2426cb2829ca2e4f5cd5f44bb2c51f46b846c56017e11db224',
	'html'        => <<<'PKITGUIDE'
<h1>Getting Started with ProducerKit</h1>
<p>Welcome! This guide walks you through setting up your website's tools — your {{place_lower}} status, availability board, events, and more. You don't need to know how to code. Everything here happens through the WordPress admin.</p>
<p>The examples below use <strong>{{trade}}</strong>'s words, because that is the trade this site is set up for. If you keep bees, throw pots, bake, print or record something else, do <strong>Step 1</strong> first — change the trade and this guide changes with it.</p>
<hr>
<h2>First Things First</h2>
<p>After the plugin is activated, you'll see a <strong>ProducerKit</strong> menu in your WordPress sidebar. Click it to see the dashboard — it shows how much content you have, your {{place_lower}}'s current status, and which parts of the plugin are switched on. You can switch off the ones you do not use, like pre-orders or made-to-order requests; it tells you what each is holding first, and never deletes anything. If anything needs attention (like products without photos or events without dates), a <strong>Needs Attention</strong> section will flag those items with direct links to fix them.</p>
<p>Two of the plugin's menus are named to stay out of the way of other plugins:
<strong>Catalog</strong> is your products (WooCommerce owns &quot;Products&quot;), and <strong>Calendar</strong> is
your events (The Events Calendar owns &quot;Events&quot;). Locations and Sources live
inside the <strong>ProducerKit</strong> menu.</p>
<hr>
<h2>Payments, QR Codes, and Pre-Orders</h2>
<p><strong>Payment options</strong>: edit a Location and open the <strong>Payment Options</strong> panel in the sidebar. Add links (Venmo, Cash App, PayPal, or a custom payment URL) and badges for other accepted payments (cash, check, SNAP/EBT, market vouchers). They appear in the Location Info block, your {{place_lower}} banner, and the location's page. With more than one location, the panel offers to copy the list from one you have already set up, so you do not type it twice.</p>
<p><strong>QR code</strong>: in the Location Info block's settings, turn on <strong>Show payment QR code</strong>. Visitors scan it to open your first payment link — and if you print the page, the code enlarges for stand signage.</p>
<p><strong>Pre-orders</strong>: add the <strong>Pre-Order Form</strong> block to a page. Visitors pick products and a pickup date and pay when they collect. Manage orders under <strong>ProducerKit → Pre-Orders</strong>: confirm them, mark them ready (the customer gets an email if they left one), and mark them picked up. Sold-out products are hidden from the form automatically. Pickup dates respect the location's weekly schedule and season, and you can block specific dates (holidays, closures) under <strong>Schedule &amp; Season → Closed Dates</strong> when editing the location.</p>
<p><strong>Taking money up front</strong>: by default a pre-order is a reservation and nothing is charged. If WooCommerce is installed and its module is on, you can mark a product to take a <strong>deposit</strong> or the <strong>full price</strong> when someone orders it — open the <strong>Pre-Order Payment</strong> panel when editing that product. A $50 deposit on a nucleus colony takes $100 for two and leaves $300 for pickup; anything else in the same order still asks for nothing. The balance is yours to collect either way: send a payment link, or take it at the table and mark it paid.</p>
<p><strong>Harvest list</strong>: on the Pre-Orders screen, click <strong>Harvest List</strong> for per-pickup-date totals of everything to have ready — print it and take it to the field.</p>
<p><strong>Fresh Sheet</strong>: under <strong>ProducerKit → Fresh Sheet</strong>, print a one-pager of today's availability with prices, your hours, payment options, and a payment QR code — the morning sign for your {{place_lower}}.</p>
<hr>
<h2>Step 1: Choose Your Trade</h2>
<p>Do this before anything else. It is one dropdown, and it decides what the rest
of the plugin calls things.</p>
<ol>
<li>Go to <strong>ProducerKit → Producer Profile</strong>.</li>
<li>Pick the <strong>producer profile</strong> that matches what you make — Farm, Bakery,
Beekeeping, Pottery, Woodworking, Jewelry, Leather, Fiber Arts, Metalwork,
Screen Printing, Painting &amp; Drawing, Taxidermy, Author, Comics, Musician,
or General.</li>
<li>Save.</li>
</ol>
<p>Everything downstream follows from it. Where a farm records a <strong>{{source_field}}</strong> and <strong>Milling / Process Notes</strong>, a beekeeper records an <strong>Apiary</strong> and
<strong>Extraction Notes</strong>, and a musician a <strong>Label</strong> and <strong>Mastering Notes</strong> — the
same three questions, in each trade's own words. The optional product fields
only exist for trades that asked for them, so a farm never sees them at all
and a potter gets <strong>Clay Body</strong>, <strong>Glaze</strong> and <strong>Firing Method</strong>. Even the word for a made-to-order request changes: a potter takes
a <strong>Commission</strong>, a beekeeper answers an <strong>Enquiry</strong>, a grower takes a
<strong>{{requests}}</strong>.</p>
<p>You can change it later and nothing is lost — switching a profile off leaves
its terms in the database — but picking it now saves renaming things around
work you have already typed.</p>
<p><strong>More than one trade on one site?</strong> You can turn on several. The fields
combine, and further down the same screen each person can pick which trade's
wording <em>they</em> read — so a farm that also bakes can have one of you reading
Milling Notes and the other reading Baking Notes.</p>
<p>The plugin also pre-loads default terms for your trade — for {{trade}} that is {{product_types_label}} ({{product_types}}), Seasons (Spring, Summer, Fall, Winter), and {{event_types_label}} ({{event_types}}). You can use these as-is or rename them.</p>
<hr>
<hr>
<h2>Step 2: Set Up Where You Sell</h2>
<p>Your roadside stand needs to exist as a Location in WordPress before your {{place_lower}} status tools will work.</p>
<ol>
<li>Go to <strong>ProducerKit → Locations</strong>, then <strong>Add New</strong>.</li>
<li><strong>Title</strong>: <code>Farm Stand</code> (or whatever you call it).</li>
<li>In the post editor sidebar, you'll see a <strong>Location Details</strong> panel with proper form fields:
<ul>
<li><strong>Location Type</strong>: Pick &quot;Farm Stand&quot; from the dropdown.</li>
<li><strong>Address</strong>: <code>123 Farm Road, Yourtown, ST 00000</code></li>
<li><strong>Hours</strong>: <code>Saturdays 1:00 – 4:00 PM, May – December</code></li>
<li><strong>Venmo Handle</strong>: Your Venmo username (without the @). A payment link is generated automatically.</li>
<li><strong>Latitude / Longitude</strong>: Optional. Used for map links.</li>
<li><strong>Currently Open</strong>: Toggle this on when your {{place_lower}} is open.</li>
<li><strong>Status Message</strong>: Optional message like &quot;Back at 2 PM&quot; shown alongside the open/closed badge.</li>
</ul>
</li>
<li>Expand the <strong>Schedule &amp; Season</strong> panel:
<ul>
<li><strong>Season Start / End</strong>: Pick your dates with the date pickers. Leave blank if open year-round.</li>
<li><strong>Auto-toggle from schedule</strong>: Turn this on if you want your {{place_lower}} to automatically open and close on schedule.</li>
<li><strong>Weekly Schedule</strong>: Click <strong>Add Day</strong> to set your {{place_lower}} hours. Pick the day, set open and close times. Add as many days as you need. The × button removes a day.</li>
</ul>
</li>
<li><strong>Publish</strong> the location.</li>
</ol>
<p>You should now see a green or red dot in the admin bar at the top of every page — that's your {{place_lower}} toggle. Click it to open or close your {{place_lower}} from anywhere.</p>
<hr>
<h2>Step 3: Add Your {{products}}</h2>
<p>Products are everything you sell — produce, bread, baked goods, seedlings, pantry items.</p>
<ol>
<li>Go to <strong>Catalog → Add New</strong>. (Your products live under Catalog, not Products — WooCommerce owns that menu.)</li>
<li><strong>Title</strong>: The product name (e.g., <code>Arugula</code>, <code>Country Sourdough</code>).</li>
<li>Add a <strong>Featured Image</strong> — this shows up on the availability board.</li>
<li>In the sidebar, you'll see a <strong>Product Details</strong> panel:
<ul>
<li><strong>Price</strong>: Whatever you want to display (e.g., <code>$4</code>, <code>$12</code>, <code>Donation</code>).</li>
<li><strong>Unit of Sale</strong>: Pick from the dropdown (bunch, loaf, pint, pound, etc.) or choose &quot;other&quot; to type a custom unit.</li>
<li><strong>{{notes_field}}</strong>: A short note shown to visitors (e.g., <code>No-till, heirloom variety</code>).</li>
</ul>
</li>
<li>If this product came from somewhere worth naming — a partner farm, a mill, a tannery, a particular hive — expand the <strong>Sources</strong> panel to link it. Create sources first under <strong>ProducerKit → Sources</strong>: each one records who it came from, where, and what was done to it in between, in your trade's own words.</li>
<li>In the right sidebar, assign a <strong>Product Type</strong> (Produce, Bread, Baked Good, Pantry Good, Seedling).</li>
<li>Assign <strong>Seasons</strong> (Spring, Summer, Fall, Winter) for when this product is typically available.</li>
<li><strong>Publish</strong>.</li>
</ol>
<p>Repeat for each product. Don't worry about getting them all in at once — you can add more throughout the season.</p>
<p><strong>Tip</strong>: The Products list table shows price and availability status at a glance. You can sort by price.</p>
<p><strong>Bulk import</strong>: If you have many products to add at once, go to <strong>ProducerKit → Product Import</strong>. You can download a CSV template (or export your existing products), edit it in a spreadsheet, and upload it to create or update products in bulk. The format reference on the page explains every column.</p>
<hr>
<h2>Step 4: Update Weekly Availability</h2>
<p>This is the task you'll do most often — probably every Saturday morning.</p>
<ol>
<li>Go to <strong>ProducerKit → Availability</strong> in the sidebar.</li>
<li>You'll see all your products in a table with thumbnails and prices. For each one:
<ul>
<li>Pick a <strong>Status</strong>: Abundant, Available, Limited, Sold Out, or leave it blank (not listed).</li>
<li>Add a <strong>Quantity Note</strong> if helpful (e.g., <code>~3 bunches left</code>, <code>Last 2 loaves</code>).</li>
</ul>
</li>
<li><strong>Shortcut</strong>: Click <strong>Copy Last Week</strong> to pre-fill from your current availability. Then just adjust the few things that changed.</li>
<li>Set the <strong>Effective Date</strong> (defaults to today).</li>
<li>Click <strong>Save All Changes</strong>.</li>
</ol>
<p>That's it — the availability board on your website updates immediately.</p>
<hr>
<h2>Step 5: Place Blocks on Your Pages</h2>
<p>Now put the tools on your actual website pages. Go to any page in the editor (or create a new one) and add blocks from the <strong>ProducerKit</strong> category:</p>
<h3>Homepage (recommended blocks)</h3>
<ul>
<li>
<p><strong>Stand Status Banner</strong>: Shows open/closed with address, hours, and Venmo link.</p>
<ul>
<li>Add the block, select your {{place_lower}} location in the sidebar.</li>
<li>Pick a layout: Banner (full-width), Compact (strip), or Card (centered).</li>
<li>Turn on &quot;Auto-refresh&quot; if you want it to update without page reload.</li>
</ul>
</li>
<li>
<p><strong>Availability Board</strong>: Shows what's available this week.</p>
<ul>
<li>Add the block, choose Grid or List layout.</li>
<li>The filters let visitors narrow by status or product type.</li>
<li>You can choose which statuses are shown by default.</li>
</ul>
</li>
</ul>
<h3>Events Page</h3>
<ul>
<li><strong>Event List</strong>: Shows upcoming events with RSVP forms.
<ul>
<li>Add the block, toggle whether to show past events too.</li>
<li>RSVP forms appear automatically for events that have RSVPs enabled.</li>
</ul>
</li>
</ul>
<h3>Anywhere</h3>
<ul>
<li><strong>Event Card</strong>: Feature a single event (like the next pizza night) on any page.</li>
<li><strong>Product Card</strong>: Highlight a specific product.</li>
<li><strong>Location Info</strong>: Show stand details in a sidebar or footer.</li>
<li><strong>Stand Hours Schedule</strong>: Show your weekly schedule in a clean table format with today's row highlighted.</li>
<li><strong>Availability Badge</strong>: Show a single product's status inline in any post or page.</li>
<li><strong>Stand Quick Toggle</strong>: An open/closed switch you can put on a private page, for opening your {{place_lower}} from a phone without the admin bar.</li>
</ul>
<h3>Taking orders</h3>
<ul>
<li><strong>Pre-Order Form</strong>: Visitors reserve products for a pickup date. See <em>Payments, QR Codes, and Pre-Orders</em> above.</li>
<li><strong>Request Form</strong>: For things you make to order — a commission, an enquiry about bulk honey, a special order. Visitors describe what they want, you quote a price and a date, and they accept or decline from a link in their email. Needs the Commissions module. The form and its emails use your trade's own word for the job, so nobody is asked to &quot;commission a piece&quot; of honey.</li>
</ul>
<hr>
<h2>Step 6: Create Your First Event</h2>
<ol>
<li>Go to <strong>Calendar → Add New</strong>. (Events live under Calendar — The Events Calendar owns that menu.)</li>
<li><strong>Title</strong>: e.g., <code>Pizza Night — June 6</code></li>
<li>Write a description in the editor.</li>
<li>In the sidebar, you'll see an <strong>Event Details</strong> panel:
<ul>
<li><strong>Start</strong>: Pick a date and time using the date and time pickers.</li>
<li><strong>End</strong>: Pick the end date and time. Defaults to the same day.</li>
<li><strong>Location</strong>: Select your Farm Stand (or another location) from the dropdown.</li>
<li><strong>Donation / Payment Link</strong>: Your Venmo link for the event.</li>
</ul>
</li>
<li>Expand the <strong>RSVP Settings</strong> panel to enable RSVPs:
<ul>
<li><strong>Enable RSVPs</strong>: Toggle on.</li>
<li><strong>RSVP Cap</strong>: Drag the slider to set a max (e.g., 30). Leave at 0 for unlimited.</li>
<li><strong>Button Label</strong>: Custom text (e.g., <code>Count me in!</code>).</li>
<li><strong>Manually Close RSVPs</strong>: Toggle on to stop taking RSVPs regardless of cap.</li>
</ul>
</li>
<li>Expand the <strong>Event Info</strong> panel:
<ul>
<li><strong>Cost / Donation Note</strong>: e.g., <code>Donation-based — suggested $10/person</code></li>
<li><strong>What to Bring</strong>: e.g., <code>A side dish or dessert to share</code></li>
<li><strong>Event Cancelled</strong>: Toggle on if you need to cancel. A cancelled badge will appear.</li>
</ul>
</li>
<li>Assign an <strong>Event Type</strong> in the right sidebar (Pizza Night, Potluck, etc.).</li>
<li><strong>Publish</strong>.</li>
</ol>
<p>The event will now appear in the Event List block and can be featured with an Event Card block.</p>
<p><strong>Tip</strong>: The Events list table shows the event date, location, and RSVP count at a glance. Events sort by date automatically, so the next upcoming event is always at the top.</p>
<hr>
<h2>Step 7: Set Up a farmers market</h2>
<p>If you're selling at a farmers market (starting May 2, 2026), create a second location:</p>
<ol>
<li><strong>ProducerKit → Locations → Add New</strong></li>
<li>Title: <code>a farmers market</code></li>
<li>Location Type: <strong>Farmers Market</strong></li>
<li>Fill in the market's address and hours.</li>
<li>Set the season dates for the market season.</li>
<li><strong>Publish</strong>.</li>
</ol>
<p>You can now set availability per-location on the Availability page, and the board will show which products are available at which location.</p>
<hr>
<h2>Step 8: Shops That Carry Your Goods</h2>
<p>If a shop, feed store or co-op stocks what you make, add it as a location with
Location Type <strong>Retailer</strong>. The hours you enter are theirs, not yours — a
customer reading the page needs to know when <em>that shop</em> is open.</p>
<ol>
<li><strong>ProducerKit → Locations → Add New</strong></li>
<li>Title: the shop's name, e.g. <code>Oil City Feed &amp; Seed</code></li>
<li>Location Type: <strong>Retailer</strong></li>
<li>Fill in the shop's address and their opening hours.</li>
<li><strong>Publish</strong>.</li>
</ol>
<p>Then record what they have, the same way you would for your own stand:
<strong>ProducerKit → Availability</strong>, choosing that shop as the location.</p>
<p>Two pages update on their own once you do:</p>
<ul>
<li><strong>The shop's page</strong> gains an &quot;Available here&quot; list — what is currently on
their shelf, with your quantity note (&quot;about 2 cases&quot;) and a <em>Running low</em>
flag when you mark something Limited.</li>
<li><strong>Each product's page</strong> gains a &quot;Where to find it&quot; line naming every shop
that has it, so someone wanting a jar today can tell which one to drive to.</li>
</ul>
<p>Marking an item <strong>Sold out</strong> at a shop removes it from both lists, rather than
sending someone on a wasted trip. Leaving a row's location blank means
&quot;available everywhere&quot;, and it shows on every shop's shelf.</p>
<p>Retailers do not need accounts. Nobody but you writes to this — you update it
from your own delivery notes.</p>
<hr>
<h2>Daily Workflow Cheat Sheet</h2>
<table>
<thead>
<tr>
<th>Task</th>
<th>Where</th>
<th>How Often</th>
</tr>
</thead>
<tbody>
<tr>
<td>Open/close your {{place_lower}}</td>
<td>Admin bar dot (any page)</td>
<td>Every stand day</td>
</tr>
<tr>
<td>Set a status message</td>
<td>Admin bar → &quot;Set Status Message…&quot;</td>
<td>As needed</td>
</tr>
<tr>
<td>Update availability</td>
<td>ProducerKit → Availability</td>
<td>Weekly (Saturday morning)</td>
</tr>
<tr>
<td>Update a shop's shelf after a delivery</td>
<td>ProducerKit → Availability</td>
<td>Each delivery</td>
</tr>
<tr>
<td>Add a new product</td>
<td>Catalog → Add New</td>
<td>As new crops/items come in</td>
</tr>
<tr>
<td>Bulk add products</td>
<td>ProducerKit → Product Import</td>
<td>Start of season</td>
</tr>
<tr>
<td>Create an event</td>
<td>Calendar → Add New</td>
<td>When planning events</td>
</tr>
<tr>
<td>Check RSVPs</td>
<td>Events list → RSVP column</td>
<td>Before each event</td>
</tr>
<tr>
<td>Check for content gaps</td>
<td>ProducerKit dashboard</td>
<td>Occasionally</td>
</tr>
</tbody>
</table>
<hr>
<h2>Loading Sample Data (for Testing)</h2>
<p>If you want to see how everything looks with example products, events, and availability before entering your real data:</p>
<ol>
<li>Go to <strong>ProducerKit</strong> dashboard.</li>
<li>Click <strong>Load Sample Data</strong>.</li>
<li>Explore the blocks on your pages to see how they look.</li>
<li>When you're ready, click <strong>Remove Sample Data</strong> to clear it all out.</li>
</ol>
<p>Sample content is labeled with amber &quot;Sample&quot; badges on the front end and a notice in the editor so you won't confuse it with real content.</p>
<hr>
<h2>Tips</h2>
<ul>
<li><strong>Featured images matter.</strong> Products with photos look much better on the availability board. Even a quick phone photo of the arugula bed or a fresh loaf is great.</li>
<li><strong>Keep excerpts short.</strong> The excerpt field on products and events shows up in cards and lists. One sentence is perfect.</li>
<li><strong>The admin bar toggle works on your phone.</strong> Open the WordPress app, visit any page on your site, and tap your {{place_lower}} status dot to open or close from the field.</li>
<li><strong>Availability expires automatically.</strong> If you set an expiration date on an availability entry, it drops off the board on its own. A daily cleanup job removes expired entries from the database.</li>
<li><strong>&quot;Copy Last Week&quot; is your friend.</strong> On the availability page, click Copy Last Week to pre-fill from current data, then adjust the few things that changed. Much faster than starting from scratch.</li>
<li><strong>Events sort by date.</strong> Upcoming events appear in chronological order. Past events move to the &quot;Past&quot; section automatically.</li>
<li><strong>The sidebar panels save with the post.</strong> All the Location, Product, and Event fields in the sidebar save when you click Update or Publish — no separate save button needed.</li>
<li><strong>Admin columns save you time.</strong> The list tables for Products, Events, and Locations show key info at a glance. Use the column headers to sort.</li>
<li><strong>You'll get email notifications.</strong> When someone RSVPs or your {{place_lower}} status is toggled, you'll get an email. These can be turned off if they get noisy — just ask Jerome.</li>
<li><strong>Export before you import.</strong> If you're doing a bulk product update, export your current products first to get the CSV format, make changes in a spreadsheet, then re-import.</li>
</ul>
<hr>
<h2>Need Help?</h2>
<p>If something isn't working right or you have ideas for improvements, open an issue on the plugin's GitHub repository.</p>
PKITGUIDE,
];
