<?php

namespace App\Exceptions;

use RuntimeException;

// A membership rule failed (BR-4.x). The message is safe to show the customer or admin.
class MembershipException extends RuntimeException {}
