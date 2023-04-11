<?php

use PHPUnit\Framework\TestCase;

require_once 'class-modena-shipping-ai.php';

class WC_Estonia_Shipping_Method_Test extends TestCase
{
    public function test_constructor()
    {
        $wc_estonia_shipping_method = new WC_Estonia_Shipping_Method();
        $this->assertCount(3, get_class_methods('WC_Estonia_Shipping_Method_Test'));
        $this->assertSame('modena-shipping-itella-terminals', $wc_estonia_shipping_method->id);
        echo "The 'id' variable has been asserted.\n";
        $this->assertSame(__('Itella pakiterminalid', 'woocommerce'), $wc_estonia_shipping_method->method_title);
        echo "The 'method_title' variable has been asserted.\n";
        $this->assertSame(__('Itella pakiterminalide lahendus Modenalt', 'woocommerce'), $wc_estonia_shipping_method->method_description);
        echo "The 'method_description' variable has been asserted.\n";
    }
}

// Instantiate the test class
$test = new WC_Estonia_Shipping_Method_Test();

// Run the test method
$test->test_constructor();

// Check if the test passed or failed and output a message to the terminal
if ($test->hasFailed()) {
    echo "The test has failed.\n";
} else {
    echo "The test has passed.\n";
}