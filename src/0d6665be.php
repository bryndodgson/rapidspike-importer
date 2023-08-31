<?php

// Accepts a CSV with a single column of domains each on a new line
// Alternatively you can amend the split method below to accept a comma-separated list of domains
// Adds a Condition to the HTTP (uptime) monitors
// Remember to set: website credit type, check condition, check phrase

require_once '_bootstrap.php';

try {

    // Split the domain list by comma
    //$arrDomainList = explode(',', $domain_list);

    // Split the domain list by new line
    $arrDomainList = preg_split('/\r\n|\r|\n/', $domain_list);

    echo count($arrDomainList) . ' domains to import!', PHP_EOL;

    $credit_type = 0;
    $monitor_testing_period = 15;
    $monitor_test_regions = ["us-west-1", "us-east-1", "us-east-2"];
    $monitor_check_condition = "contains";
    $monitor_check_phrase = "phrase-to-check";

    foreach ($arrDomainList as $key => $domain_name) {
        $objClient = new RapidSpike\API\Client($params['public_key'], $params['private_key'], $params['url']);
        
        // domain_name = store9061.marcos.com, label is the number
        $label = str_replace('.marcos.com', '', str_replace('store', '', $domain_name));

        // Add a schema
        $domain_name = "https://{$domain_name}";

        // Report some things
        $i = $key + 1;
        echo "#{$i}: {$domain_name}", PHP_EOL;
        echo "  Label: {$label}", PHP_EOL;
        echo "  Domain: {$domain_name}", PHP_EOL;

        /*
         * CREATE THE WEBSITE
         */
        try {
            usleep(500000);
            echo "  Creating...", PHP_EOL;

            // Build create website POST body
            $arrCreateBody = array(
                'label' => $label,
                'domain_name' => $domain_name,
                'monitor_testing_period' => $monitor_testing_period,
                'credit_type' => $credit_type
            );

            // Make the POST request to `/websites` - create the website
            $objCreateRes = $objClient->websites()
                    ->addJsonBody($arrCreateBody)
                    ->via('post');

            if (!empty($objCreateRes->error_code)) {
                throw new Exception("Creating the website failed: {$objCreateRes->message}");
            }

            // Extract the new website's UUID
            $website_uuid = $objCreateRes->data->website->uuid;
            echo "  UUID: {$website_uuid}", PHP_EOL;
        } catch (Exception $e) {
            echo 'ERRORS', PHP_EOL, $e->getMessage(), PHP_EOL, PHP_EOL;
            sleep(1);
            continue;
        }

        /*
         * UPDATE THE WEBSITE'S TEST REGIONS
         */
        try {
            echo "  Setting test regions...", PHP_EOL;

            // Update the website's test regions
            $objUpdateRes = $objClient->websites($website_uuid)
                    ->addJsonBody(['monitor_test_regions' => $monitor_test_regions])
                    ->via('put');

            if (!empty($objUpdateRes->error_code)) {
                throw new Exception("Updating the website failed: {$objUpdateRes->message}");
            }
        } catch (Exception $e) {
            echo 'ERRORS', PHP_EOL, $e->getMessage(), PHP_EOL, PHP_EOL;
            sleep(2);
            continue;
        }

        /*
         * CREATE THE HTTP MONITOR
         */
        try {
            echo "  Creating a HTTP monitor...", PHP_EOL;

            $arrMonitorBody = array(
                'website_uuid' => $website_uuid,
                'http_monitors' => [
                    [
                        'label' => 'Home',
                        'target' => '/',
                        'expected_http_code' => 200,
                        'condition_type' => $monitor_check_condition,
                        'condition_value' => $monitor_check_phrase
                    ]
                ]
            );

            $objCreateRes = $objClient->httpmonitors()->addJsonBody($arrMonitorBody)->via('post');

            if (!empty($objCreateRes->error_code)) {
                throw new Exception("Creating the HTTP monitor failed: {$objCreateRes->message}");
            }
        } catch (Exception $e) {
            echo 'ERRORS', PHP_EOL, $e->getMessage(), PHP_EOL, PHP_EOL;
            sleep(3);
            continue;
        }

        echo "  All good, moving on after 0.5 seconds.", PHP_EOL;
    }
} catch (\Exception $e) {
    echo 'Fail', PHP_EOL, $e->getMessage(), PHP_EOL, PHP_EOL;
    echo json_encode($e, JSON_PRETTY_PRINT), PHP_EOL;
}

echo PHP_EOL, 'Completed', PHP_EOL;
exit;
