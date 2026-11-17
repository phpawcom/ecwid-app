<?php

namespace App\Payments\Exceptions;

use RuntimeException;

/**
 * A payment problem whose message can be shown to the shopper or merchant.
 */
class PaymentException extends RuntimeException {}
