<?php
/**
 * Backward compatibility redirect shim.
 * Relocated client-facing bookings page to public/MyBookings.php
 */
header("Location: ../MyBookings.php", true, 301);
exit();
