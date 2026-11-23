<?php

declare(strict_types=1);

namespace Vendor\PestProgress\Compat;

use Pest\Contracts\Plugins\Shutdownable;
use Pest\Contracts\Plugins\Terminable;

// Early Pest 2 called shutdown() through its Shutdownable contract.
if (interface_exists(Shutdownable::class)) {
    class_alias(Shutdownable::class, __NAMESPACE__.'\Termination');
} else {
    interface Termination extends Terminable {}
}
