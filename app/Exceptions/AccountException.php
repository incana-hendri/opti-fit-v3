<?php

namespace App\Exceptions;

use RuntimeException;

// An account rule failed (BR-1.8, 9.4, 9.6). The message is safe to show the admin.
class AccountException extends RuntimeException {}
