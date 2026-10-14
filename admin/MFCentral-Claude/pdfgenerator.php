<?php

require __DIR__ . '/../vendor/autoload.php';

class PDFGenerator
{
    public function generate($data, $outputFilename = "CGS_Report.pdf")
    {
        // 10mm margins
        $mpdf = new \Mpdf\Mpdf(['orientation' => 'L', 'margin_top' => 10, 'margin_bottom' => 10, 'margin_left' => 10, 'margin_right' => 10]); 
        
        $html = '<style>
            body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 8pt; color: #000; }
            .main-title { font-size: 16pt; color: #1a237e; font-weight: bold; text-align: center; margin-bottom: 2px; }
            .period-title { font-size: 9pt; color: #1a237e; text-align: center; font-weight: bold; margin-bottom: 15px; }
            
            .header-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; border: 1px solid #000; }
            .header-table td { vertical-align: top; padding: 10px; }
            .header-table td:first-child { border-right: 1px solid #000; }
            .info-box { color: #1a237e; font-size: 8pt; text-align: justify; }
            
            .broker-info { color: #1a237e; font-weight: bold; margin-top: 15px; font-size: 9pt; }
            
            .scheme-mergers { color: #7f8c8d; font-size: 7.5pt; margin-bottom: 15px; text-align: justify; }
            
            .amc-header { width: 100%; border-bottom: 1px solid #000; margin-bottom: 5px; font-size: 8pt; font-weight: bold; }
            .amc-header td { padding: 3px; }
            
            .tx-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 7pt; }
            .tx-table th, .tx-table td { border: none; padding: 4px 6px; text-align: right; word-wrap: break-word; }
            .tx-table td:nth-child(1), .tx-table td:nth-child(2), .tx-table td:nth-child(7), .tx-table td:nth-child(8) { text-align: left; }
            .tx-table td:nth-child(6), .tx-table th:nth-child(6),
            .tx-table td:nth-child(12), .tx-table th:nth-child(12),
            .tx-table td:nth-child(15), .tx-table th:nth-child(15),
            .tx-table td:nth-child(18), .tx-table th:nth-child(18) { border-right: 1px solid #000; }
            
            .th-dark { background-color: #0b2246; color: #fff; font-weight: bold; border-right: 1px solid #000; text-align: center; }
            .th-light { background-color: #e0e0e0; font-weight: bold; text-align: right; border-bottom: 1px solid #fff; border-right: 1px solid #fff; }
            
            .th-summary-title { background-color: #0b2246; color: #fff; font-weight: bold; text-align: left; }
            .th-summary-col { background-color: #e0e0e0; color: #000; font-weight: bold; text-align: right; border-right: 1px solid #fff; }
            .th-summary-col-left { background-color: #e0e0e0; color: #000; font-weight: bold; text-align: left; border-right: 1px solid #fff; }
            
            .scheme-title-row { background-color: #4db6ac; color: #000; font-weight: bold; text-align: left !important; }
            .scheme-title-row td { text-align: left !important; }
            
            .totals-row { background-color: #fce4ec; font-weight: bold; }
            
            .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 8pt; }
            .summary-table th, .summary-table td { border: none; padding: 4px 6px; text-align: right; word-wrap: break-word; }
            .summary-table td:nth-child(1) { text-align: left; }
            .summary-table th.th-summary-col-left { text-align: left; }
            .keep-together { page-break-inside: avoid; }
            
            .disclaimer { font-size: 7pt; margin-top: 20px; font-weight: bold; }
        </style>';

        if (empty($data['data']) || !is_array($data['data'])) {
            $html .= "<div class='main-title'>Capital Gain / (Loss) Statement</div>";
            $html .= "<p>No data available in this statement.</p>";
            $mpdf->WriteHTML($html);
            $mpdf->Output($outputFilename, \Mpdf\Output\Destination::FILE);
            return;
        }

        $static = $data['data'][0]['staticDetails'][0] ?? [];
        $summary = $data['data'][0]['summaryDetails'] ?? [];
        $transactions = $data['data'][0]['transactionDetails'] ?? [];

        $fromDate = $data['fromDate'] ?? '';
        $toDate = $data['toDate'] ?? '';

        // Header Title
        $html .= "<div class='main-title'>Capital Gain / (Loss) Statement</div>";
        if ($fromDate && $toDate) {
            $html .= "<div class='period-title'>For the period {$fromDate} To {$toDate}<br/><span style='font-weight:normal; font-size:8pt;'>Generated On: " . date('d-M-Y') . "</span></div>";
        } else {
            $html .= "<div style='text-align:center; margin-bottom: 15px; font-size:8pt;'>Generated On: " . date('d-M-Y') . "</div>";
        }

        // Top Boxes
        $html .= "<table class='header-table'>
                    <tr>
                        <td width='50%' style='border-right: 1px solid #000;'>
                            <div style='color: #1a237e; font-weight: bold; font-size: 9pt;'>
                                Email: " . htmlspecialchars($static['email'] ?? $data['email'] ?? '') . "<br/><br/>
                                " . htmlspecialchars($static['invName'] ?? 'N/A') . "<br/><br/>
                            </div>
                            <div style='color: #1a237e; font-size: 8pt; font-weight: bold;'>
                                " . htmlspecialchars($static['address1'] ?? '') . "<br/>";
        if (!empty($static['address2'])) $html .= htmlspecialchars($static['address2']) . "<br/>";
        if (!empty($static['address3'])) $html .= htmlspecialchars($static['address3']) . "<br/>";
        $html .= htmlspecialchars($static['city'] ?? '') . " - " . htmlspecialchars($static['pinCode'] ?? '') . "<br/>";
        $html .= htmlspecialchars($static['state'] ?? '') . "<br/>India<br/>";
        $html .= "Mobile: " . htmlspecialchars($data['mobile'] ?? '') . "
                            </div>
                            <div class='broker-info'>
                                <br/><br/>
                                Broker Code: ARN-166563<br/>
                                Broker Name: LITM FINANCIAL PVT LTD
                            </div>
                        </td>
                        <td width='50%'>
                            <div class='info-box'>
                                This statement provides your Investment Performance, Capital Gains and Income for the current and last Financial Years, consolidated across MF Central serviced funds. Statement will contain actual withdrawals made by you from your fund account, realised gains/losses on a FIFO basis and segregated as long term and short term. The statement now includes LTCG applicable for equity oriented schemes introduced in the Budget 2018. The statements will be provided for the previous and current financial year.<br/><br/>
                                The consolidation has been carried out based on email id entered by you. If you do not have entered a PAN and if the email id is common to several members of your family, this statement will consolidate all those investments as well.<br/><br/>
                                Should you find any folio missing in this consolidation, please check for the registration of your email id in the respective folio.
                            </div>
                        </td>
                    </tr>
                  </table>";

        // Mergers Note
        $html .= "<div class='scheme-mergers'>
                    <strong>Scheme Mergers and Scheme Name changes by Mutual Funds</strong><br/>
                    Mutual funds in India have been mandated by SEBI to re-categorize and harmonize their existing schemes. The objective of this exercise is to eliminate multiple scheme offerings in the same category (by a given fund house) and to adopt a uniform system of scheme categorization across the industry. Pursuant to this, Mutual funds have renamed certain schemes in order to meet SEBI's requirement. Additionally they have merged some of their schemes whereby an existing scheme would have been merged into another similar scheme. It is possible that schemes you have invested in may have undergone either a name change or a merger. Please visit www.mfcentral.com for details on schemes that have merged or have undergone a name change. Please note that in respect of scheme mergers the original investment value in the erstwhile scheme (scheme which has got merged) is considered for the purpose of determining cost value.
                  </div>";

        $schemes = $data['data'][0]['schemeDetails'] ?? [];

        // Summary Section
        if (!empty($summary)) {
            $html .= "<table class='summary-table keep-together' style='margin-top: 0; page-break-inside: avoid;'>
                        <tr>
                            <td colspan='7' style='background-color: #0b2246; color: #fff; font-weight: bold; padding: 6px; text-align: left;'>Consolidated Capital Gains Summary</td>
                        </tr>
                        <tr>
                            <th class='th-summary-col-left' style='padding:4px 6px;'>Summary Details</th>
                            <th class='th-summary-col' style='padding:4px 6px;'>01-Apr to 15-Jun</th>
                            <th class='th-summary-col' style='padding:4px 6px;'>16-Jun to 15-Sep</th>
                            <th class='th-summary-col' style='padding:4px 6px;'>16-Sep to 15-Dec</th>
                            <th class='th-summary-col' style='padding:4px 6px;'>16-Dec to 15-Mar</th>
                            <th class='th-summary-col' style='padding:4px 6px;'>16-Mar to 31-Mar</th>
                            <th class='th-summary-col' style='padding:4px 6px; border-right:none;'>Total</th>
                        </tr>";
            $summaryCount = count(array_filter($summary, function($i) { return isset($i['summaryOfCapitalGains']); }));
            $sIdx = 0;
            foreach ($summary as $item) {
                if (isset($item['summaryOfCapitalGains'])) {
                    $sIdx++;
                    $border = ($sIdx === $summaryCount) ? "" : "border-bottom:1px solid #ccc;";
                    $html .= "<tr style='{$border}'>";
                    $html .= "<td style='text-align:left; padding:4px 6px;'>" . htmlspecialchars($item['summaryOfCapitalGains']) . "</td>";
                    $html .= "<td style='text-align:right; padding:4px 6px;'>" . htmlspecialchars($item['01/04 to 15/06'] ?? '0.00') . "</td>";
                    $html .= "<td style='text-align:right; padding:4px 6px;'>" . htmlspecialchars($item['16/06 to 15/09'] ?? '0.00') . "</td>";
                    $html .= "<td style='text-align:right; padding:4px 6px;'>" . htmlspecialchars($item['16/09 to 15/12'] ?? '0.00') . "</td>";
                    $html .= "<td style='text-align:right; padding:4px 6px;'>" . htmlspecialchars($item['16/12 to 15/03'] ?? '0.00') . "</td>";
                    $html .= "<td style='text-align:right; padding:4px 6px;'>" . htmlspecialchars($item['16/03 to 31/03'] ?? '0.00') . "</td>";
                    $html .= "<td style='text-align:right; font-weight:bold; padding:4px 6px;'>" . htmlspecialchars($item['total'] ?? '0.00') . "</td>";
                    $html .= "</tr>";
                }
            }
            $html .= "</table>";
        }
        
        // Scheme Details Section
        if (!empty($schemes)) {
            $html .= "<div style='margin-top: 20px;'>";
            $html .= "<div style='background-color: #0b2246; color: #fff; font-weight: bold; padding: 6px; text-align: left;'>Scheme-wise Capital Gains Breakdown</div>";
            $html .= "<table class='summary-table' style='font-size:7pt; margin-top: 0;'>
                        <tr>
                            <th class='th-summary-col-left' style='padding:6px;'>Scheme Name</th>
                            <th class='th-summary-col' style='padding:6px;'>Asset<br/>Class</th>
                            <th class='th-summary-col' style='padding:6px;'>Txn<br/>Count</th>
                            <th class='th-summary-col' style='padding:6px;'>Total<br/>Amount</th>
                            <th class='th-summary-col' style='padding:6px;'>Total<br/>Cost</th>
                            <th class='th-summary-col' style='padding:6px;'>Indexed<br/>Cost</th>
                            <th class='th-summary-col' style='padding:6px;'>Short<br/>Term</th>
                            <th class='th-summary-col' style='padding:6px;'>LT (Without<br/>Indexation)</th>
                            <th class='th-summary-col' style='padding:6px;'>LT (With<br/>Indexation)</th>
                            <th class='th-summary-col' style='padding:6px; border-right:none;'>TDS</th>
                        </tr>";
            $schemesCount = count($schemes);
            $scIdx = 0;
            foreach ($schemes as $scheme) {
                $scIdx++;
                $border = ($scIdx === $schemesCount) ? "" : "border-bottom:1px solid #ccc;";
                $html .= "<tr style='{$border}'>";
                $html .= "<td style='text-align:left; padding:6px;'>" . htmlspecialchars($scheme['schemeName(Redeem/Switchout)'] ?? 'N/A') . "</td>";
                $html .= "<td style='text-align:right; padding:6px;'>" . htmlspecialchars($scheme['assetClass'] ?? 'N/A') . "</td>";
                $html .= "<td style='text-align:right; padding:6px;'>" . htmlspecialchars($scheme['totalCount'] ?? '0') . "</td>";
                $html .= "<td style='text-align:right; padding:6px;'>" . htmlspecialchars($scheme['totalAmount'] ?? '0.00') . "</td>";
                $html .= "<td style='text-align:right; padding:6px;'>" . htmlspecialchars($scheme['totalCost'] ?? '0.00') . "</td>";
                $html .= "<td style='text-align:right; padding:6px;'>" . htmlspecialchars($scheme['indexedCost'] ?? '0.00') . "</td>";
                $html .= "<td style='text-align:right; padding:6px;'>" . htmlspecialchars($scheme['shortTerm'] ?? '0.00') . "</td>";
                $html .= "<td style='text-align:right; padding:6px;'>" . htmlspecialchars($scheme['longTermWithoutIndexation'] ?? '0.00') . "</td>";
                $html .= "<td style='text-align:right; padding:6px;'>" . htmlspecialchars($scheme['longTermWithIndex'] ?? '0.00') . "</td>";
                $html .= "<td style='text-align:right; padding:6px;'>" . htmlspecialchars($scheme['tds'] ?? '0.00') . "</td>";
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
        
        $html .= "<pagebreak />";

        // Group transactions by AMC, Folio, Scheme
        $schemesGrouped = [];
        foreach ($transactions as $txn) {
            $amc = $txn['amcName'] ?? 'Unknown AMC';
            $folio = $txn['folio'] ?? 'Unknown Folio';
            $scheme = $txn['schemeName'] ?? 'Unknown Scheme';
            $key = "$amc|$folio|$scheme";
            
            if (!isset($schemesGrouped[$key])) {
                $schemesGrouped[$key] = [
                    'amcName' => $amc,
                    'folio' => $folio,
                    'schemeName' => $scheme,
                    'pan' => $txn['pan'] ?? ($static['pan'] ?? ''),
                    'status' => $txn['status'] ?? ($static['status'] ?? 'Individual'),
                    'investor' => $txn['name'] ?? ($static['invName'] ?? ''),
                    'txns' => []
                ];
            }
            $schemesGrouped[$key]['txns'][] = $txn;
        }

        // Render each grouped scheme
        foreach ($schemesGrouped as $grp) {
            // AMC Header
            $html .= "<div class='amc-header' style='padding: 3px 0;'>
                        AMC : <strong>" . htmlspecialchars($grp['amcName']) . "</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        FOLIO No. <strong>" . htmlspecialchars($grp['folio']) . "</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        NAME : <strong>" . htmlspecialchars($grp['investor']) . "</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        STATUS : <strong>" . htmlspecialchars($grp['status']) . "</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        PAN No. <strong>" . htmlspecialchars($grp['pan']) . "</strong>
                      </div>";
                      
            $html .= "<div style='font-weight:bold; font-size: 8pt; margin-bottom: 2px;'>Equity / Debt Schemes</div>";

            // Transactions Table
            $html .= "<table class='tx-table'>";
            // Top Headers
            $html .= "<tr>
                        <th colspan='6' class='th-dark' style='text-align:left;'>A : Redemption / Switch-Out</th>
                        <th colspan='6' class='th-dark' style='text-align:left;'>B: Corresponding Units in Purchase / Switch-In / Div. Reinvested</th>
                        <th colspan='3' class='th-dark'>C. Grandfathered Investments as<br/>on 31st Jan 2018</th>
                        <th colspan='3' class='th-dark'>D : Capital Gains / (Losses)</th>
                        <th colspan='3' class='th-dark' style='border-right:none;'>E : TDS (if applicable)</th>
                      </tr>";
            // Sub Headers
            $html .= "<tr>
                        <th class='th-light left'>Description</th>
                        <th class='th-light left'>Date</th>
                        <th class='th-light'>Units</th>
                        <th class='th-light'>Amount<br/>&#8377;</th>
                        <th class='th-light'>Price<br/>&#8377;</th>
                        <th class='th-light'>STT</th>
                        
                        <th class='th-light left'>Description</th>
                        <th class='th-light left'>Date</th>
                        <th class='th-light'>Purchase<br/>Units</th>
                        <th class='th-light'>Redeemed<br/>Units</th>
                        <th class='th-light'>Unit Cost<br/>&#8377;</th>
                        <th class='th-light'>Indexed<br/>Cost**&#8377;</th>
                        
                        <th class='th-light'>Units as on<br/>31/01/2018</th>
                        <th class='th-light'>Nav as on<br/>31/01/2018</th>
                        <th class='th-light'>Market<br/>value as on<br/>31/01/2018</th>
                        
                        <th class='th-light'>Short Term</th>
                        <th class='th-light'>Long Term with<br/>Indexation<br/>&#8377;</th>
                        <th class='th-light'>Long Term without<br/>Indexation<br/>&#8377;</th>
                        
                        <th class='th-light'>Tax<br/>Perc</th>
                        <th class='th-light'>Tax<br/>Deducted<br/>&#8377;</th>
                        <th class='th-light' style='border-right:none;'>Tax+<br/>Surcharge</th>
                      </tr>";
            
            // Scheme row
            $html .= "<tr class='scheme-title-row'>
                        <td colspan='21'>" . htmlspecialchars($grp['schemeName']) . "</td>
                      </tr>";

            $currentSellLeg = null;
            $totals = ['shortTerm' => 0, 'longTermWithIndex' => 0, 'longTermWithoutIndex' => 0, 'taxDeducted' => 0, 'taxSurcharge' => 0];

            foreach ($grp['txns'] as $txn) {
                $sellKey = ($txn['desc_1'] ?? '') . '|' . ($txn['date_1'] ?? '') . '|' . ($txn['redUnits'] ?? '');
                
                $html .= "<tr style='background-color: " . ($currentSellLeg === $sellKey ? "#f9f9f9" : "#ffffff") . "'>";
                
                if ($sellKey !== $currentSellLeg) {
                    // New redemption leg
                    $html .= "<td>" . htmlspecialchars($txn['desc_1'] ?? '') . "</td>";
                    $html .= "<td>" . htmlspecialchars($txn['date_1'] ?? '') . "</td>";
                    $html .= "<td>" . htmlspecialchars(number_format((float)($txn['redUnits'] ?? 0), 3)) . "</td>";
                    $html .= "<td>" . htmlspecialchars(number_format((float)($txn['amount'] ?? 0), 2)) . "</td>"; 
                    $html .= "<td>" . htmlspecialchars(number_format((float)($txn['price'] ?? 0), 4)) . "</td>";
                    $html .= "<td>" . htmlspecialchars($txn['stt'] ?? '0') . "</td>";
                    $currentSellLeg = $sellKey;
                } else {
                    // Same redemption leg, blank A columns
                    $html .= "<td colspan='6'></td>";
                }

                // B columns (Purchase)
                $html .= "<td>" . htmlspecialchars($txn['desc'] ?? '') . "</td>";
                $html .= "<td>" . htmlspecialchars($txn['date'] ?? '') . "</td>";
                $html .= "<td>" . htmlspecialchars(number_format((float)($txn['purhUnit'] ?? ($txn['units'] ?? 0)), 3)) . "</td>";
                $html .= "<td>" . htmlspecialchars(number_format((float)($txn['redUnits'] ?? 0), 3)) . "</td>";
                $html .= "<td>" . htmlspecialchars(number_format((float)($txn['unitCost'] ?? 0), 4)) . "</td>";
                $html .= "<td>" . htmlspecialchars(number_format((float)($txn['indexedCost'] ?? 0), 4)) . "</td>";

                // C columns (Grandfathered)
                $html .= "<td>" . htmlspecialchars(number_format((float)($txn['grandfatheredUnits(As On 31/01/2018)'] ?? 0), 3)) . "</td>";
                $html .= "<td>" . htmlspecialchars(number_format((float)($txn['grandfatheredNAV(As On 31/01/2018)'] ?? 0), 4)) . "</td>";
                $html .= "<td>" . htmlspecialchars(number_format((float)($txn['grandfatheredMarketValue(As On 31/01/2018 )'] ?? 0), 2)) . "</td>";

                // D columns (Gains)
                $st = (float)($txn['shortTerm'] ?? 0);
                $ltIdx = (float)($txn['longTermWithIndex'] ?? 0);
                $ltNoIdx = (float)($txn['longTermWithoutIndex'] ?? 0);
                
                $html .= "<td>" . number_format($st, 2) . "</td>";
                $html .= "<td>" . number_format($ltIdx, 2) . "</td>";
                $html .= "<td>" . number_format($ltNoIdx, 2) . "</td>";

                // E columns (TDS)
                $taxPerc = (float)($txn['taxPerc'] ?? 0);
                $taxDed = (float)($txn['taxDeduct'] ?? 0);
                $taxSur = (float)($txn['taxSurcharge'] ?? 0);
                
                $html .= "<td>" . number_format($taxPerc, 2) . "</td>";
                $html .= "<td>" . number_format($taxDed, 2) . "</td>";
                $html .= "<td>" . number_format($taxSur, 2) . "</td>";

                $html .= "</tr>";
                
                $totals['shortTerm'] += $st;
                $totals['longTermWithIndex'] += $ltIdx;
                $totals['longTermWithoutIndex'] += $ltNoIdx;
                $totals['taxDeducted'] += $taxDed;
                $totals['taxSurcharge'] += $taxSur;
            }
            
            // Scheme Total
            $html .= "<tr class='totals-row'>
                        <td colspan='15' style='text-align:left;'>Total</td>
                        <td>" . number_format($totals['shortTerm'], 2) . "</td>
                        <td>" . number_format($totals['longTermWithIndex'], 2) . "</td>
                        <td>" . number_format($totals['longTermWithoutIndex'], 2) . "</td>
                        <td></td>
                        <td>" . number_format($totals['taxDeducted'], 2) . "</td>
                        <td>" . number_format($totals['taxSurcharge'], 2) . "</td>
                      </tr>";
                      
            $html .= "</table>";
        }
        
        $html .= "<div class='disclaimer' style='background-color: #eef2f3; padding: 10px; margin-top: 20px; font-weight: normal; font-size: 7.5pt; text-align: justify; color: #000;'>
                    *Disclaimer:This statement of gain/loss is issued at your request as additional information and should not be considered as the basis for determining your tax liability. For the purpose of determining capital gains, the first in first out ('FIFO') principle of appropriating withdrawals against subscriptions is followed. This statement is generated without considering any Indexation benefits, based on latest amendments to Finance Act, 2024. Pursuant to the Amendments made by the Finance Act 2018, the units disclosed under Section C – 'Grandfathered Investments as on 31st Jan 2018' relates to the number of units that were considered for the redemption (withdrawal), where the cost value would be the market value shown as of 31st Jan 2018. For equity mergers, the grandfathered value as on 31st Jan 2018 is considered and disclosed as NAV as on 31/01/2018', duly adjusted as per units created in target scheme. In respect of scheme mergers within the same Asset Class, the original investment value in the erstwhile scheme (scheme which merged) is considered and is reflected in Section B, as 'Unit Cost' which is adjusted as per units created in target scheme, for ease of verification. Please note that in respect of schemes where segregated portfolio is created, the original investment value of those investments prior to such creation, has been adjusted, basis the segregated percentile, for the purpose of Capital Gain.<br><br>
                    The statement shows actual STT and Tax deducted from your withdrawals and displays your tax status as on the date of generation of the statement. You are advised to consult your financial/tax advisor to verify the correctness and appropriateness of the contents of this statement. The Mutual Fund, Trustees, Asset Management Company, MF Central and each of their directors, employees or agents will not be liable for either determining your tax liability or any loss or damage arising out of any decisions/actions arising out of the contents of this statement.<br><br>
                    For Gifting on units, you are advised to consult your financial advisor on the correctness and appropriateness of tax amount mentioned in the statement.
                  </div>";

        $mpdf->WriteHTML($html);
        
        // Output to file
        $mpdf->Output($outputFilename, \Mpdf\Output\Destination::FILE);
    }
}