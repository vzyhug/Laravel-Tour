# Hotel booking MVP

Hotel inventory is manually maintained per room type and stay date. Missing dates are unavailable. Checkout date is excluded. Capacity is reserved for 15 minutes at hold creation, not again at confirmation. Existing bookings keep nightly price and cancellation-policy snapshots.

Payment and refund are explicitly simulated via `hotel_payments`, independent of tour payments. This MVP does not send a hotel supplier reservation or charge money. It must not be described as confirmed by an external hotel supplier.

Integration: require `routes/hotel_booking.php` from web routes and `routes/hotel_booking_schedule.php` from console routes. Run Laravel scheduler every minute in deployment. Expiry also happens when attempting late payment. The scheduler is necessary to release abandoned holds without customer interaction.

Opt-in demo only: `php artisan db:seed --class=HotelBookingDemoSeeder`. Creates a fictitious DEMO hotel and demo rates/inventory; does not alter real hotels or overwrite existing inventory. Do not add this seeder to production/default seeding.

Admin/Sale: `/admin/hotel-booking` creates room types and edits daily capacity/rates; cannot lower capacity below allocated rooms. Completing confirmed stays is permitted from checkout date. Customer: `/hotels/{slug}/rooms`, `/account/hotel-bookings`.

All room allocation/return paths serialize on room type; holds also lock daily inventory in date order. Booking transitions lock the booking row. Retry operations do not reserve/release twice. SQL row locking requires MySQL/PostgreSQL; SQLite tests validate sequential exhaustion and rollback, not true concurrent contention. Run concurrent database integration tests before production.

Automatic booking deletion and room-inventory deletion are intentionally restricted by foreign keys to retain snapshots and financial history. Cancellation before check-in returns all reserved nights and marks simulated payment refunded.
