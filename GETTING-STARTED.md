# Getting Started with ProducerKit

Welcome! This guide walks you through setting up your website's farm tools — the stand status, availability board, events, and more. You don't need to know how to code. Everything here happens through the WordPress admin.

---

## First Things First

After the plugin is activated, you'll see a **ProducerKit** menu in your WordPress sidebar. Click it to see the dashboard — it shows which modules are active, how much content you have, and your stand's current status. If anything needs attention (like products without photos or events without dates), a **Needs Attention** section will flag those items with direct links to fix them.

The plugin also pre-loads default terms for Product Types (Produce, Bread, Baked Good, Pantry Good, Seedling), Seasons (Spring, Summer, Fall, Winter), and Event Types (Pizza Night, Potluck, Farm Dinner, Workshop, Farm Tour, Seed Exchange, Mini Market). You can use these as-is or rename them.

---

## Payments, QR Codes, and Pre-Orders

**Payment options**: edit a Location and open the **Payment Options** panel in the sidebar. Add links (Venmo, Cash App, PayPal, or a custom payment URL) and badges for other accepted payments (cash, check, SNAP/EBT, market vouchers). They appear in the Location Info block, the stand banner, and the location's page.

**QR code**: in the Location Info block's settings, turn on **Show payment QR code**. Visitors scan it to open your first payment link — and if you print the page, the code enlarges for stand signage.

**Pre-orders**: add the **Pre-Order Form** block to a page. Visitors pick products and a pickup date and pay when they collect. Manage orders under **ProducerKit → Pre-Orders**: confirm them, mark them ready (the customer gets an email if they left one), and mark them picked up. Sold-out products are hidden from the form automatically. Pickup dates respect the location's weekly schedule and season, and you can block specific dates (holidays, closures) under **Schedule & Season → Closed Dates** when editing the location.

**Harvest list**: on the Pre-Orders screen, click **Harvest List** for per-pickup-date totals of everything to have ready — print it and take it to the field.

**Fresh Sheet**: under **ProducerKit → Fresh Sheet**, print a one-pager of today's availability with prices, your hours, payment options, and a payment QR code — the morning sign for the stand.

---

## Step 1: Set Up Your Stand Location

Your roadside stand needs to exist as a Location in WordPress before the stand status tools will work.

1. Go to **Locations → Add New** in the sidebar.
2. **Title**: `Farm Stand` (or whatever you call it).
3. In the post editor sidebar, you'll see a **Location Details** panel with proper form fields:
   - **Location Type**: Pick "Farm Stand" from the dropdown.
   - **Address**: `123 Farm Road, Yourtown, ST 00000`
   - **Hours**: `Saturdays 1:00 – 4:00 PM, May – December`
   - **Venmo Handle**: Your Venmo username (without the @). A payment link is generated automatically.
   - **Latitude / Longitude**: Optional. Used for map links.
   - **Currently Open**: Toggle this on when the stand is open.
   - **Status Message**: Optional message like "Back at 2 PM" shown alongside the open/closed badge.
4. Expand the **Schedule & Season** panel:
   - **Season Start / End**: Pick your dates with the date pickers. Leave blank if open year-round.
   - **Auto-toggle from schedule**: Turn this on if you want the stand to automatically open and close on schedule.
   - **Weekly Schedule**: Click **Add Day** to set your stand hours. Pick the day, set open and close times. Add as many days as you need. The × button removes a day.
5. **Publish** the location.

You should now see a green or red dot in the admin bar at the top of every page — that's your stand toggle. Click it to open or close the stand from anywhere.

---

## Step 2: Add Your Products

Products are everything you sell — produce, bread, baked goods, seedlings, pantry items.

1. Go to **Products → Add New**.
2. **Title**: The product name (e.g., `Arugula`, `Country Sourdough`).
3. Add a **Featured Image** — this shows up on the availability board.
4. In the sidebar, you'll see a **Product Details** panel:
   - **Price**: Whatever you want to display (e.g., `$4`, `$12`, `Donation`).
   - **Unit of Sale**: Pick from the dropdown (bunch, loaf, pint, pound, etc.) or choose "other" to type a custom unit.
   - **Growing / Baking Notes**: A short note shown to visitors (e.g., `No-till, heirloom variety`).
5. If this product uses grains from a specific farm or source, expand the **Sources** panel to link source posts.
6. In the right sidebar, assign a **Product Type** (Produce, Bread, Baked Good, Pantry Good, Seedling).
7. Assign **Seasons** (Spring, Summer, Fall, Winter) for when this product is typically available.
8. **Publish**.

Repeat for each product. Don't worry about getting them all in at once — you can add more throughout the season.

**Tip**: The Products list table shows price and availability status at a glance. You can sort by price.

**Bulk import**: If you have many products to add at once, go to **ProducerKit → Product Import**. You can download a CSV template (or export your existing products), edit it in a spreadsheet, and upload it to create or update products in bulk. The format reference on the page explains every column.

---

## Step 3: Update Weekly Availability

This is the task you'll do most often — probably every Saturday morning.

1. Go to **ProducerKit → Availability** in the sidebar.
2. You'll see all your products in a table with thumbnails and prices. For each one:
   - Pick a **Status**: Abundant, Available, Limited, Sold Out, or leave it blank (not listed).
   - Add a **Quantity Note** if helpful (e.g., `~3 bunches left`, `Last 2 loaves`).
3. **Shortcut**: Click **Copy Last Week** to pre-fill from your current availability. Then just adjust the few things that changed.
4. Set the **Effective Date** (defaults to today).
5. Click **Save All Changes**.

That's it — the availability board on your website updates immediately.

---

## Step 4: Place Blocks on Your Pages

Now put the tools on your actual website pages. Go to any page in the editor (or create a new one) and add blocks from the **ProducerKit** category:

### Homepage (recommended blocks)

- **Stand Status Banner**: Shows open/closed with address, hours, and Venmo link.
  - Add the block, select your stand location in the sidebar.
  - Pick a layout: Banner (full-width), Compact (strip), or Card (centered).
  - Turn on "Auto-refresh" if you want it to update without page reload.

- **Availability Board**: Shows what's available this week.
  - Add the block, choose Grid or List layout.
  - The filters let visitors narrow by status or product type.
  - You can choose which statuses are shown by default.

### Events Page

- **Event List**: Shows upcoming events with RSVP forms.
  - Add the block, toggle whether to show past events too.
  - RSVP forms appear automatically for events that have RSVPs enabled.

### Anywhere

- **Event Card**: Feature a single event (like the next pizza night) on any page.
- **Product Card**: Highlight a specific product.
- **Location Info**: Show stand details in a sidebar or footer.
- **Stand Hours Schedule**: Show your weekly schedule in a clean table format with today's row highlighted.
- **Availability Badge**: Show a single product's status inline in any post or page.

---

## Step 5: Create Your First Event

1. Go to **Events → Add New**.
2. **Title**: e.g., `Pizza Night — June 6`
3. Write a description in the editor.
4. In the sidebar, you'll see an **Event Details** panel:
   - **Start**: Pick a date and time using the date and time pickers.
   - **End**: Pick the end date and time. Defaults to the same day.
   - **Location**: Select your Farm Stand (or another location) from the dropdown.
   - **Donation / Payment Link**: Your Venmo link for the event.
5. Expand the **RSVP Settings** panel to enable RSVPs:
   - **Enable RSVPs**: Toggle on.
   - **RSVP Cap**: Drag the slider to set a max (e.g., 30). Leave at 0 for unlimited.
   - **Button Label**: Custom text (e.g., `Count me in!`).
   - **Manually Close RSVPs**: Toggle on to stop taking RSVPs regardless of cap.
6. Expand the **Event Info** panel:
   - **Cost / Donation Note**: e.g., `Donation-based — suggested $10/person`
   - **What to Bring**: e.g., `A side dish or dessert to share`
   - **Event Cancelled**: Toggle on if you need to cancel. A cancelled badge will appear.
7. Assign an **Event Type** in the right sidebar (Pizza Night, Potluck, etc.).
8. **Publish**.

The event will now appear in the Event List block and can be featured with an Event Card block.

**Tip**: The Events list table shows the event date, location, and RSVP count at a glance. Events sort by date automatically, so the next upcoming event is always at the top.

---

## Step 6: Set Up the Jonesborough Farmers Market

If you're selling at the Jonesborough Farmers Market (starting May 2, 2026), create a second location:

1. **Locations → Add New**
2. Title: `Jonesborough Farmers Market`
3. Location Type: **Farmers Market**
4. Fill in the market's address and hours.
5. Set the season dates for the market season.
6. **Publish**.

You can now set availability per-location on the Availability page, and the board will show which products are available at which location.

---

## Step 7: Shops That Carry Your Goods

If a shop, feed store or co-op stocks what you make, add it as a location with
Location Type **Retailer**. The hours you enter are theirs, not yours — a
customer reading the page needs to know when *that shop* is open.

1. **Locations → Add New**
2. Title: the shop's name, e.g. `Oil City Feed & Seed`
3. Location Type: **Retailer**
4. Fill in the shop's address and their opening hours.
5. **Publish**.

Then record what they have, the same way you would for your own stand:
**ProducerKit → Availability**, choosing that shop as the location.

Two pages update on their own once you do:

- **The shop's page** gains an "Available here" list — what is currently on
  their shelf, with your quantity note ("about 2 cases") and a *Running low*
  flag when you mark something Limited.
- **Each product's page** gains a "Where to find it" line naming every shop
  that has it, so someone wanting a jar today can tell which one to drive to.

Marking an item **Sold out** at a shop removes it from both lists, rather than
sending someone on a wasted trip. Leaving a row's location blank means
"available everywhere", and it shows on every shop's shelf.

Retailers do not need accounts. Nobody but you writes to this — you update it
from your own delivery notes.

---

## Daily Workflow Cheat Sheet

| Task | Where | How Often |
|------|-------|-----------|
| Open/close the stand | Admin bar dot (any page) | Every stand day |
| Set a status message | Admin bar → "Set Status Message…" | As needed |
| Update availability | ProducerKit → Availability | Weekly (Saturday morning) |
| Update a shop's shelf after a delivery | ProducerKit → Availability | Each delivery |
| Add a new product | Products → Add New | As new crops/items come in |
| Bulk add products | ProducerKit → Product Import | Start of season |
| Create an event | Events → Add New | When planning events |
| Check RSVPs | Events list → RSVP column | Before each event |
| Check for content gaps | ProducerKit dashboard | Occasionally |

---

## Loading Sample Data (for Testing)

If you want to see how everything looks with example products, events, and availability before entering your real data:

1. Go to **ProducerKit** dashboard.
2. Click **Load Sample Data**.
3. Explore the blocks on your pages to see how they look.
4. When you're ready, click **Remove Sample Data** to clear it all out.

Sample content is labeled with amber "Sample" badges on the front end and a notice in the editor so you won't confuse it with real content.

---

## Tips

- **Featured images matter.** Products with photos look much better on the availability board. Even a quick phone photo of the arugula bed or a fresh loaf is great.
- **Keep excerpts short.** The excerpt field on products and events shows up in cards and lists. One sentence is perfect.
- **The admin bar toggle works on your phone.** Open the WordPress app, visit any page on your site, and tap the stand status dot to open or close from the field.
- **Availability expires automatically.** If you set an expiration date on an availability entry, it drops off the board on its own. A daily cleanup job removes expired entries from the database.
- **"Copy Last Week" is your friend.** On the availability page, click Copy Last Week to pre-fill from current data, then adjust the few things that changed. Much faster than starting from scratch.
- **Events sort by date.** Upcoming events appear in chronological order. Past events move to the "Past" section automatically.
- **The sidebar panels save with the post.** All the Location, Product, and Event fields in the sidebar save when you click Update or Publish — no separate save button needed.
- **Admin columns save you time.** The list tables for Products, Events, and Locations show key info at a glance. Use the column headers to sort.
- **You'll get email notifications.** When someone RSVPs or the stand status is toggled, you'll get an email. These can be turned off if they get noisy — just ask Jerome.
- **Export before you import.** If you're doing a bulk product update, export your current products first to get the CSV format, make changes in a spreadsheet, then re-import.

---

## Need Help?

If something isn't working right or you have ideas for improvements, open an issue on the plugin's GitHub repository.