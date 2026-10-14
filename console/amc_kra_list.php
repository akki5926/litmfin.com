<?php
if(!isset($_SESSION)){ session_start(); }
if(!(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] == true)){
    http_response_code(403);
    exit("Unauthorized access.");
}

require_once('../admin/database.php');
$db = new MYSQLDB();

$amcs = [];
try {
    // Get unique AMC codes and their RTA mapping
    $query = "
        SELECT a.AMC_CODE, a.LONG_NAME, s.rta_agent_code 
        FROM mtb_amc a 
        LEFT JOIN mtb_scheme_master s ON a.AMC_CODE = s.amc_code 
        GROUP BY a.AMC_CODE, a.LONG_NAME, s.rta_agent_code
        ORDER BY a.LONG_NAME ASC
    ";
    
    $res = $db->query($query);
    while($row = $res->fetch_assoc()) {
        $rta = strtoupper(trim($row['rta_agent_code'] ?? ''));
        // Map KARVY to KFintech for display
        $displayRta = ($rta === 'KARVY') ? 'KFINTECH' : $rta;
        
        if (in_array($displayRta, ['CAMS', 'KFINTECH'])) {
            $amcs[$displayRta][$row['AMC_CODE']] = $row;
        }
        $amcs['ALL'][$row['AMC_CODE']] = $row;
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="fw-bold mb-1" style="color: #1e2340;">AMC List by KRA (RTA)</h4>
            <p class="text-muted small mb-0">View Mutual Fund AMCs categorized by their Registrar and Transfer Agents (CAMS / KFintech).</p>
        </div>
    </div>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-body p-4">
            <ul class="nav nav-tabs" id="rtaTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-tab-pane" type="button" role="tab" aria-controls="all-tab-pane" aria-selected="true" style="color: #1e2340;">ALL</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold" id="cams-tab" data-bs-toggle="tab" data-bs-target="#cams-tab-pane" type="button" role="tab" aria-controls="cams-tab-pane" aria-selected="false" style="color: #1e2340;">CAMS</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold" id="kfintech-tab" data-bs-toggle="tab" data-bs-target="#kfintech-tab-pane" type="button" role="tab" aria-controls="kfintech-tab-pane" aria-selected="false" style="color: #1e2340;">KFintech (KARVY)</button>
                </li>
            </ul>
            
            <div class="tab-content pt-4" id="rtaTabsContent">
                <!-- ALL TAB -->
                <div class="tab-pane fade show active" id="all-tab-pane" role="tabpanel" aria-labelledby="all-tab" tabindex="0">
                    <div class="table-responsive">
                        <style>
                            th.sortable { cursor: pointer; user-select: none; position: relative; padding-right: 20px; }
                            th.sortable:hover { background-color: #f1f3f5; }
                            th.sortable::after { content: '\2195'; position: absolute; right: 8px; color: #ccc; font-size: 0.8rem; top: 50%; transform: translateY(-50%); }
                            th.sortable.asc::after { content: '\2191'; color: #1e2340; }
                            th.sortable.desc::after { content: '\2193'; color: #1e2340; }
                        </style>
                        <table class="table table-hover align-middle" id="table-all">
                            <thead class="table-light">
                                <tr>
                                    <th>S.No</th>
                                    <th class="sortable" onclick="sortTable('table-all', 1)">AMC Name</th>
                                    <th class="sortable" onclick="sortTable('table-all', 2)">AMC Code</th>
                                    <th class="sortable" onclick="sortTable('table-all', 3)">KRA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if(!empty($amcs['ALL'])) {
                                    $sn = 1;
                                    foreach($amcs['ALL'] as $amc) {
                                        $displayRta = strtoupper(trim($amc['rta_agent_code'] ?? ''));
                                        $displayRta = ($displayRta === 'KARVY') ? 'KFINTECH' : $displayRta;
                                        if (empty($displayRta)) $displayRta = '-';
                                        
                                        $kraBadge = '';
                                        if ($displayRta == 'CAMS') {
                                            $kraBadge = '<span class="badge rounded-pill bg-light text-dark border border-secondary" style="font-size:0.7rem;">CAMS</span>';
                                        } else if ($displayRta == 'KFINTECH') {
                                            $kraBadge = '<span class="badge rounded-pill bg-light text-dark border border-primary" style="font-size:0.7rem;">KFINTECH</span>';
                                        } else {
                                            $kraBadge = '<span class="text-muted">'.$displayRta.'</span>';
                                        }

                                        echo '<tr>';
                                        echo '<td>'.$sn++.'</td>';
                                        echo '<td class="fw-medium">'.htmlspecialchars($amc['LONG_NAME']).'</td>';
                                        echo '<td class="text-muted"><code>'.htmlspecialchars($amc['AMC_CODE']).'</code></td>';
                                        echo '<td>'.$kraBadge.'</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="4" class="text-center text-muted py-3">No AMCs found.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

<script>
function sortTable(tableId, n) {
  var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
  table = document.getElementById(tableId);
  switching = true;
  dir = "asc"; 
  
  var ths = table.getElementsByTagName("TH");
  for(var j=0; j<ths.length; j++) {
      if(ths[j].classList.contains('sortable')){
          if(j !== n) ths[j].classList.remove('asc', 'desc');
      }
  }

  if(ths[n].classList.contains('asc')) {
      dir = "desc";
      ths[n].classList.remove('asc');
      ths[n].classList.add('desc');
  } else {
      ths[n].classList.remove('desc');
      ths[n].classList.add('asc');
  }

  while (switching) {
    switching = false;
    rows = table.rows;
    for (i = 1; i < (rows.length - 1); i++) {
      shouldSwitch = false;
      x = rows[i].getElementsByTagName("TD")[n];
      y = rows[i + 1].getElementsByTagName("TD")[n];
      if (!x || !y) continue;
      
      var cmpX = x.innerHTML.toLowerCase().replace(/<[^>]+>/g, '').trim();
      var cmpY = y.innerHTML.toLowerCase().replace(/<[^>]+>/g, '').trim();
      
      if (dir == "asc") {
        if (cmpX > cmpY) {
          shouldSwitch = true;
          break;
        }
      } else if (dir == "desc") {
        if (cmpX < cmpY) {
          shouldSwitch = true;
          break;
        }
      }
    }
    if (shouldSwitch) {
      rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
      switching = true;
      switchcount ++;
    }
  }
  
  // Re-number S.No
  rows = table.rows;
  for (i = 1; i < rows.length; i++) {
      rows[i].getElementsByTagName("TD")[0].innerHTML = i;
  }
}
</script>

                <!-- CAMS TAB -->
                <div class="tab-pane fade" id="cams-tab-pane" role="tabpanel" aria-labelledby="cams-tab" tabindex="0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>S.No</th>
                                    <th>AMC Name</th>
                                    <th>AMC Code</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if(!empty($amcs['CAMS'])) {
                                    $sn = 1;
                                    foreach($amcs['CAMS'] as $amc) {
                                        echo '<tr>';
                                        echo '<td>'.$sn++.'</td>';
                                        echo '<td class="fw-medium">'.htmlspecialchars($amc['LONG_NAME']).'</td>';
                                        echo '<td class="text-muted"><code>'.htmlspecialchars($amc['AMC_CODE']).'</code></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="3" class="text-center text-muted py-3">No AMCs found for CAMS.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- KFINTECH TAB -->
                <div class="tab-pane fade" id="kfintech-tab-pane" role="tabpanel" aria-labelledby="kfintech-tab" tabindex="0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>S.No</th>
                                    <th>AMC Name</th>
                                    <th>AMC Code</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if(!empty($amcs['KFINTECH'])) {
                                    $sn = 1;
                                    foreach($amcs['KFINTECH'] as $amc) {
                                        echo '<tr>';
                                        echo '<td>'.$sn++.'</td>';
                                        echo '<td class="fw-medium">'.htmlspecialchars($amc['LONG_NAME']).'</td>';
                                        echo '<td class="text-muted"><code>'.htmlspecialchars($amc['AMC_CODE']).'</code></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="3" class="text-center text-muted py-3">No AMCs found for KFintech.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>
