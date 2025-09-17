<?php
/**
 * PHPStorm Meta file for WordPress function recognition
 * This helps IDEs understand WordPress functions that are loaded at runtime
 */

namespace PHPSTORM_META {

    // WordPress functions that may not be recognized
    expectedArguments(\add_action(), 0, argumentsSet('wordpress_hooks'));
    expectedArguments(\add_filter(), 0, argumentsSet('wordpress_hooks'));

    registerArgumentsSet('wordpress_hooks',
        'init',
        'gform_webhooks_request_data',
        'gform_zapier_request_body'
    );
}