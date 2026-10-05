ALTER TABLE booking_details
  ADD COLUMN IF NOT EXISTS refund_reason VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS refund_processed_at DATETIME NULL,
  ADD KEY IF NOT EXISTS idx_booking_refund (refund_status);
