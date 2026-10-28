# Hotel booking MVP

Hotel inventory is date-specific; missing dates are unavailable and checkout date is excluded. `HotelBookingCatalogSeeder` initializes a VietGo reference room and rate for the catalog hotels for the next 90 nights (900,000–1,500,000 VND per room/night, two reference rooms per night). These are project-set reference values, not supplier-confirmed prices or availability. Staff can edit daily price and capacity; checkout date is excluded. Capacity is reserved for 15 minutes at hold creation, not again at confirmation. Existing bookings keep nightly price and cancellation-policy snapshots.

Google Maps catalog ratings, addresses and reviews are not hotel inventory or supplier confirmation. Hotel pages show the internal room-search action only when the hotel has an active VietGo room type. Otherwise the customer sees that VietGo has no published inventory and can continue to the hotel's own website. No scraped price is copied into `hotel_room_inventory`.

Payment and refund are explicitly simulated via `hotel_payments`, independent of tour payments. This MVP does not send a hotel supplier reservation or charge money. It must not be described as confirmed by an external hotel supplier.

Integration: require `routes/hotel_booking.php` from web routes and `routes/hotel_booking_schedule.php` from console routes. Run Laravel scheduler every minute in deployment. Expiry also happens when attempting late payment. The scheduler is necessary to release abandoned holds without customer interaction.

Opt-in demo only: `php artisan db:seed --class=HotelBookingDemoSeeder`. Creates a fictitious DEMO hotel and demo rates/inventory; does not alter real hotels or overwrite existing inventory. Do not add this seeder to production/default seeding.

Admin/Sale: `/admin/hotel-booking` creates room types and edits daily capacity/rates; cannot lower capacity below allocated rooms. Completing confirmed stays is permitted from checkout date. Customer: `/hotels/{slug}/rooms`, `/account/hotel-bookings`.

All room allocation/return paths serialize on room type; holds also lock daily inventory in date order. Booking transitions lock the booking row. Retry operations do not reserve/release twice. SQL row locking requires MySQL/PostgreSQL; SQLite tests validate sequential exhaustion and rollback, not true concurrent contention. Run concurrent database integration tests before production.

Automatic booking deletion and room-inventory deletion are intentionally restricted by foreign keys to retain snapshots and financial history. Cancellation before check-in returns all reserved nights and marks simulated payment refunded.

## Midnight and service validation safeguards

A same-day hold expires no later than midnight at the end of its check-in date. Holds for future check-in retain the full 15-minute window across midnight. Payment, cancellation and the expiry job also expire older pending holds whose check-in date has already passed, even if their stored expiry timestamp is later. This prevents confirming a new stay with a past check-in date.

The service validates quantities and strict calendar dates before allocation, in addition to HTTP validation, so calls from commands or other services cannot allocate zero/negative rooms or normalize invalid dates. Regression tests cover midnight boundaries, old pending holds and invalid service inputs. SQLite checks sequential allocation and release only; MySQL concurrency remains to be verified.
