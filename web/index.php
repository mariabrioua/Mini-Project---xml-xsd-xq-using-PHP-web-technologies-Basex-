<?php

//  Club Info_Tech — Competition Management
//  index.php  |  XML data source: ../club.xml


$xml_path = '../club.xml';
$message  = '';
$error    = '';

//  Helper: load XML 
function loadXML($path) {
    if (!file_exists($path)) return null;
    return simplexml_load_file($path);
}

// Score formula 
// Score = (tempsExecution + complexite) * coefficient
function calcScore($complexite, $temps, $coefficient) {
    return round(($temps + $complexite) * $coefficient, 2);
}

// BaseX REST API helper 
// Sends an XQuery string to BaseX HTTP server and returns raw result or error
function runXQuery($query) {
    $basex_url  = 'http://localhost:8080/rest';
    $basex_user = 'admin';
    $basex_pass = 'admin';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $basex_url . '?query=' . urlencode($query),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => "$basex_user:$basex_pass",
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Accept: application/xml'],
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) return ['ok' => false, 'result' => "cURL error: $curl_err"];
    if ($http_code >= 400) return ['ok' => false, 'result' => "BaseX error (HTTP $http_code):\n$response"];
    return ['ok' => true, 'result' => $response];
}

// Handle free XQuery (POST) 
$xquery_result  = null;
$xquery_ok      = null;
$xquery_input   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'xquery') {
    $xquery_input = trim($_POST['xquery'] ?? '');
    if ($xquery_input === '') {
        $xquery_ok     = false;
        $xquery_result = 'Please enter a query.';
    } else {
        $res           = runXQuery($xquery_input);
        $xquery_ok     = $res['ok'];
        $xquery_result = $res['result'];
    }
}

// Handle inscription (POST) 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'inscrire') {
    $concoursId = trim($_POST['concours']     ?? '');
    $membreId   = trim($_POST['membre']       ?? '');
    $complexite = intval($_POST['complexite'] ?? 0);
    $temps      = intval($_POST['temps']      ?? 0);

    if (!$concoursId || !$membreId) {
        $error = "Please select a competition and a member.";
    } elseif ($complexite < 0 || $complexite > 100) {
        $error = "Complexity must be between 0 and 100.";
    } elseif ($temps <= 0) {
        $error = "Execution time must be greater than 0.";
    } else {
        $xml = loadXML($xml_path);
        if ($xml) {
            $found = false;
            foreach ($xml->concours->concours as $c) {
                if ((string)$c['id'] === $concoursId) {
                    $participant = $c->participants->addChild('participant');
                    $participant->addAttribute('membreRef', $membreId);
                    $participant->addChild('complexite', $complexite);
                    $participant->addChild('tempsExecution', $temps);
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $dom = dom_import_simplexml($xml)->ownerDocument;
                $dom->formatOutput = true;
                $dom->save($xml_path);
                $message = "Registration saved successfully!";
            } else {
                $error = "Competition not found.";
            }
        } else {
            $error = "Could not read the XML file.";
        }
    }
}

// Load data 
$xml        = loadXML($xml_path);
$categories = [];
$membres    = [];
$concoursList   = [];

if ($xml) {
    foreach ($xml->categories->categorie as $cat) {
        $categories[(string)$cat['id']] = (string)$cat['libelle'];
    }
    foreach ($xml->membres->membre as $m) {
        $membres[(string)$m['id']] = (string)$m->prenom . ' ' . (string)$m->nom;
    }
    foreach ($xml->concours->concours as $c) {
        $concoursList[] = $c;
    }
}

// Selected competition for results 
$selectedId      = $_GET['view_concours'] ?? '';
$results         = [];
$selectedConcour = null;

if ($selectedId && $xml) {
    foreach ($xml->concours->concours as $c) {
        if ((string)$c['id'] === $selectedId) {
            $selectedConcour = $c;
            $coeff = (float)$c['coefficient'];
            foreach ($c->participants->participant as $p) {
                $mid   = (string)$p['membreRef'];
                $comp  = (int)$p->complexite;
                $temps = (int)$p->tempsExecution;
                $results[] = [
                    'nom'        => $membres[$mid] ?? $mid,
                    'complexite' => $comp,
                    'temps'      => $temps,
                    'score'      => calcScore($comp, $temps, $coeff),
                ];
            }
            usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Club Info_Tech — Competition Management</title>
  <link rel="stylesheet" href="style.css">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>

  <!-- HEADER -->
  <header class="site-header">
    <div class="header-inner">
      <div class="header-badge">XML</div>
      <div>
        <h1>Club <span>Info_Tech</span></h1>
        <p class="subtitle">Competition Management System</p>
      </div>
      <div class="header-trophy">🏆</div>
    </div>
  </header>

  <main class="container">

    <?php if ($message): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- SECTION 1 : Competition List -->
    <section class="card" id="competitions-section">
      <div class="card-header">
        <span class="card-icon">📅</span>
        <h2>Available Competitions</h2>
      </div>

      <?php if (empty($concoursList)): ?>
        <p class="empty">No competitions found in the XML file.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Date</th>
              <th>Category</th>
              <th>Coefficient</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($concoursList as $c):
              $catLabel = $categories[(string)$c['categorieRef']] ?? (string)$c['categorieRef'];
            ?>
            <tr>
              <td class="td-title"><?= htmlspecialchars((string)$c->titre) ?></td>
              <td><?= htmlspecialchars((string)$c['date']) ?></td>
              <td><span class="badge"><?= htmlspecialchars($catLabel) ?></span></td>
              <td class="td-coeff"><?= htmlspecialchars((string)$c['coefficient']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>

    <!-- SECTION 2 : Results  -->
    <section class="card" id="results-anchor">
      <div class="card-header">
        <span class="card-icon">🥇</span>
        <h2>Competition Results</h2>
      </div>

      <form method="GET" action="index.php" class="select-form">
        <select name="view_concours" class="select-input">
          <option value="">— Select a competition —</option>
          <?php foreach ($concoursList as $c): ?>
            <option value="<?= htmlspecialchars((string)$c['id']) ?>"
              <?= ($selectedId === (string)$c['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)$c->titre) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Show Results</button>
      </form>

      <?php if ($selectedId && empty($results)): ?>
        <p class="empty">No participants registered for this competition yet.</p>
      <?php elseif (!empty($results)): ?>
      <div class="table-wrap">
        <table class="results-table">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Participant</th>
              <th>Complexity</th>
              <th>Time (ms)</th>
              <th>Score</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($results as $i => $r): ?>
            <tr class="<?= $i === 0 ? 'rank-first' : ($i === 1 ? 'rank-second' : ($i === 2 ? 'rank-third' : '')) ?>">
              <td class="td-rank">
                <?php
                  if ($i === 0)      echo '1 🥇';
                  elseif ($i === 1)  echo '2 🥈';
                  elseif ($i === 2)  echo '3 🥉';
                  else               echo $i + 1;
                ?>
              </td>
              <td><?= htmlspecialchars($r['nom']) ?></td>
              <td><?= $r['complexite'] ?></td>
              <td><?= $r['temps'] ?></td>
              <td class="td-score"><?= $r['score'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>

    <!-- SECTION 3 : New Registration -->
    <section class="card" id="registration-section">
      <div class="card-header">
        <span class="card-icon">📝</span>
        <h2>New Registration</h2>
      </div>

      <form method="POST" action="index.php#registration-section" class="inscription-form">
        <input type="hidden" name="action" value="inscrire">

        <div class="form-row">
          <div class="form-group">
            <label for="concours">Competition:</label>
            <select name="concours" id="concours" class="select-input" required>
              <option value="">Select...</option>
              <?php foreach ($concoursList as $c): ?>
                <option value="<?= htmlspecialchars((string)$c['id']) ?>">
                  <?= htmlspecialchars((string)$c->titre) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="membre">Member:</label>
            <select name="membre" id="membre" class="select-input" required>
              <option value="">Select...</option>
              <?php foreach ($membres as $id => $nom): ?>
                <option value="<?= htmlspecialchars($id) ?>">
                  <?= htmlspecialchars($nom) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="complexite">Algorithm Complexity (0–100):</label>
            <input type="number" name="complexite" id="complexite"
                   min="0" max="100" class="text-input" placeholder="e.g. 70" required>
          </div>

          <div class="form-group">
            <label for="temps">Execution Time (ms):</label>
            <input type="number" name="temps" id="temps"
                   min="1" class="text-input" placeholder="e.g. 150" required>
          </div>
        </div>

        <button type="submit" class="btn btn-submit">Register</button>
      </form>
    </section>

    <!-- SECTION 4 : Free XQuery  -->
    <section class="card" id="xquery-section">
      <div class="card-header">
        <span class="card-icon">⚡</span>
        <h2>Free XQuery Console</h2>
      </div>

      <div class="xquery-info">
        <p>Type any XQuery expression below and run it directly against the BaseX database.</p>
        <div class="xquery-examples">
          <span class="example-label">Quick examples:</span>
          <button type="button" class="example-btn" onclick="setQuery(this)">List all members</button>
          <button type="button" class="example-btn" onclick="setQuery(this)">List competitions</button>
          <button type="button" class="example-btn" onclick="setQuery(this)">Count participants</button>
          <button type="button" class="example-btn" onclick="setQuery(this)">Find winner CO1</button>
        </div>
      </div>

      <form method="POST" action="index.php#xquery-section" class="xquery-form">
        <input type="hidden" name="action" value="xquery">
        <div class="xquery-editor-wrap">
          <textarea name="xquery" id="xquery-input" class="xquery-textarea"
                    placeholder='e.g.  for $m in doc("club/club.xml")//membre return $m/nom/text()'
                    spellcheck="false"><?= htmlspecialchars($xquery_input) ?></textarea>
        </div>
        <div class="xquery-actions">
          <button type="submit" class="btn btn-run">&#9654; Run Query</button>
          <button type="button" class="btn btn-clear" onclick="clearQuery()">&#x2715; Clear</button>
        </div>
      </form>

      <?php if ($xquery_result !== null): ?>
      <div class="xquery-result-wrap">
        <div class="result-header">
          <span class="result-label <?= $xquery_ok ? 'label-ok' : 'label-err' ?>">
            <?= $xquery_ok ? '&#x2705; Result' : '&#x274C; Error' ?>
          </span>
        </div>
        <pre class="xquery-result <?= $xquery_ok ? 'result-ok' : 'result-err' ?>"><?= htmlspecialchars($xquery_result) ?></pre>
      </div>
      <?php endif; ?>

    </section>

  </main>

  <script>
    const examples = {
      'List all members':
        'for $m in doc("club/club.xml")//membre\nreturn <member>{string($m/prenom)} {string($m/nom)}</member>',
      'List competitions':
        'for $c in doc("club/club.xml")//concours/concours\norder by $c/@date\nreturn <competition>{string($c/titre)} — {string($c/@date)}</competition>',
      'Count participants':
        'let $doc := doc("club/club.xml")\nfor $c in $doc//concours/concours\nreturn <count concours="{string($c/titre)}">{count($c//participant)}</count>',
      'Find winner CO1':
        'let $doc := doc("club/club.xml")\nlet $c := $doc//concours/concours[@id="CO1"]\nlet $max := max(for $p in $c//participant return (xs:integer($p/complexite)+xs:integer($p/tempsExecution))*xs:decimal($c/@coefficient))\nfor $p in $c//participant\nlet $score := (xs:integer($p/complexite)+xs:integer($p/tempsExecution))*xs:decimal($c/@coefficient)\nwhere $score = $max\nreturn <winner score="{$score}">{string($doc//membre[@id=$p/@membreRef]/prenom)} {string($doc//membre[@id=$p/@membreRef]/nom)}</winner>'
    };

    function setQuery(btn) {
      const q = examples[btn.textContent.trim()];
      if (q) document.getElementById('xquery-input').value = q;
    }

    function clearQuery() {
      document.getElementById('xquery-input').value = '';
    }
  </script>

  <footer class="site-footer">
    <p>Club Info_Tech &mdash; XML Mini Project &mdash; <?= date('Y') ?></p>
  </footer>

</body>
</html>