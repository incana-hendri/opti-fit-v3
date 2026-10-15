<?php

namespace App\Exceptions;

use RuntimeException;

// A credit rule failed (BR-5.x, 6.5). The message is safe to show the customer or admin.
class CreditException extends RuntimeException {}
