<?php
// Include the initialization file
require_once dirname(dirname(__FILE__)) . '/init.php';

try {
    if (!isset($_SESSION['user'])) {
        // Get AdWordsUser from credentials in "../auth.ini"
        // relative to the AdWordsUser.php file's directory.
        $user = new AdWordsUser();
        $user->SetOAuth2Info(loadAuth(AUTH_INFO));
        // Log every SOAP XML request and response.
        $user->LogAll();
    } else {
        $user = $_SESSION['user'];
    }

    // Download the report to a file in the same directory as the example.
    $filePath = dirname(__FILE__) . '/report.csv';

    $reportType = 'CAMPAIGN_PERFORMANCE_REPORT';
    $reportName = 'Campaign performance report #' . uniqid();
    $fields = array('CampaignId', 'CampaignName', 'AccountDescriptiveName', 'BudgetId', 'CampaignStatus', 'ServingStatus', 'Impressions', 'Clicks',
        'Ctr', 'AverageCpc', 'Cost', 'Conversions');

    if (!isset($_SESSION['childAccounts'])) {
        $childAccounts = GetAccountHierarchy($user);
        $_SESSION['childAccounts'] = $childAccounts;
    } else {
        $childAccounts = $_SESSION['childAccounts'];
    }

    echo '
        <div>
            <table class="responstable">
                <tr>
                    <th>#</th>
                    <th>Campaign</th>
                    <th>Client account</th>
                    <th>Budget</th>
                    <!--
                    <th>IsExplicitlyShared </th>
                    <th>DeliveryMethod</th>
                    -->
                    <th>Status</th>
                    <!-- <th>ServingStatus</th> -->
                    <th>Impr.</th>
                    <th>Clicks</th>
                    <th>CTR</th>
                    <th>Avg.CPC</th>
                    <th>Cost</th>
                    <th>Conversions</th>
                </tr>
            <tbody>';

    $campaigns = array();
    foreach ($childAccounts as $childAccount) {

        $user->SetClientCustomerId($childAccount->customerId);

        $accountName = $childAccount->name;
        $accountId = $childAccount->customerId;
        $currencyCode = $childAccount->currencyCode;
        $str_currency = convertToCurrency($currencyCode);
        $dataTimeZone = $childAccount->dateTimeZone;

        $campaignService = $user->GetService('CampaignService', ADWORDS_VERSION);
        // Create selector.
        $selector = new Selector();
        $selector->fields = array('Id', 'Name', 'BudgetId', 'BudgetName', 'Amount', 'ServingStatus', 'Status', 'StartDate', 'EndDate');

        //'BudgetName', 'Labels', 'IsBudgetExplicitlyShared', 'BudgetStatus', 'BudgetReferenceCount', 'DeliveryMethod'

        $selector->ordering[] = new OrderBy('Name', 'ASCENDING');

        // Create paging controls.
        $selector->paging = new Paging(0, AdWordsConstants::RECOMMENDED_PAGE_SIZE);

        do {
            // Make the get request.
            $page = $campaignService->get($selector);
            // Display results.
            if (isset($page->entries)) {

                foreach ($page->entries as $campaign) {
                    $campaignName = $campaign->name;
                    $campaignId = $campaign->id;
                    $campaigns[$campaignId] = $campaign;
                    $campaignStatus = $campaign->status;
                    $endDate = $campaign->endDate;

                    if ($campaignStatus === 'ENABLED')
                        $campaignStatus = 'Eligible';
                    if ($endDate == date('Ymd', time()))
                        $campaignStatus = 'Ended on Today';
                    if ($endDate < date('Ymd', time()))
                        $campaignStatus = 'Ended';

                    $campaignServingStatus = $campaign->servingStatus;
                    if ($campaignServingStatus === 'SERVING')
                        $campaignServingStatus = 'Serving';

                    $budget = (object)$campaign->budget;
                    $budgetName = $budget->name;
                    $budgetId = $budget->budgetId;
                    $budgetAmount = $budget->amount->microAmount / 1000000;
                    //$budgetPeriod = $budget->period;
                    //if ($budgetPeriod === 'DAILY')
                    //  $budgetPeriod = 'day';
//                        $budgetIsExplicitlyShared = $budget->isExplicitlyShared;
//                        $budgetDeliveryMethod = $budget->deliveryMethod;
//                        $budgetReferenceCount = $budget->referenceCount;

                    DownloadPerformanceReport($user, $filePath, $reportType, $reportName, $fields, 'TODAY');
                    $csvFile = file($filePath);
                    $data = [];
                    foreach ($csvFile as $line) {
                        $data[] = str_getcsv($line);
                    }
                    $impressions = $data[2][6];
                    $clicks = $data[2][7];
                    $ctr = $data[2][8];
                    $averageCpc = $data[2][9];
                    $cost = $data[2][10];
                    $conversions = $data[2][11];

                    $str_date = getDuringTime($data[0][0]);

                    $str = '<tr><td width="20px"><input type="checkbox" style="display: block" /></td>' .
                        '<td width="420px">' . $campaignName . '</td>' .
                        '<td width="250px">' . $accountName . '</td>' .
                        '<td>' . $budgetName . '</br>' . $str_currency . $budgetAmount . '</td>' .
//                              '<td width="100px">' . $budgetIsExplicitlyShared . '</td>' .
//                              '<td width="100px">' . $budgetDeliveryMethod . '</td>' .
                        '<td width="90px">' . $campaignStatus . '</td>' .
//                              '<td width="120px">' . $campaignServingStatus . '</td>' .
                        '<td width="70px">' . $impressions . '</td>' .
                        '<td width="70px">' . $clicks . '</td>' .
                        '<td width="100px">' . $ctr . '</td>' .
                        '<td width="70px">' . $averageCpc . '</td>' .
                        '<td width="50px">' . $str_currency . $cost . '</td>' .
                        '<td width="50px">' . $conversions . $cost . '</td>' .
                        '</tr>';
                    echo $str;
                }
            }
            $selector->paging->startIndex += AdWordsConstants::RECOMMENDED_PAGE_SIZE;
        } while ($page->totalNumEntries > $selector->paging->startIndex);
    }
    $count = count($campaigns);
    if (isset($str_date))
        echo '<tr><td colspan="10"><b>Total - all ' . $count . ' campaigns, Time: ' . $str_date . '</b></td></tr></tbody></table></div>';
    else
        echo '<tr><td colspan="10"><b>Display no campaigns by account.</b></td></tr></tbody></table></div>';

} catch (Exception $e) {
    printf("An error has occurred: %s\n", $e->getMessage());
}
?>

