<?php

namespace App\Core\Plugins;

/**
 * Base exception for all plugin system errors.
 *
 * Exceptions from the plugin system are intentionally non-fatal —
 * a bad plugin should never crash the kernel. Callers catch this
 * and record the error in the registry's invalid bucket.
 */
class PluginException extends \Exception
{
}
