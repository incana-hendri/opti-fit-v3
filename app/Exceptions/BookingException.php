<?php

namespace App\Exceptions;

use RuntimeException;

// A booking rule failed (BR-6.x, 7.x). The message is safe to show the customer or admin.
class BookingException extends RuntimeException {}
