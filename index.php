<?php
#ini_set('display_errors', 1);
#ini_set('display_startup_errors', 1);
#error_reporting(E_ALL);

$password = "adminPassword"; // change this

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Restricted"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required.';
    exit;
} else {
    if ($_SERVER['PHP_AUTH_PW'] !== $password) {
        header('WWW-Authenticate: Basic realm="Retry Login"');
        echo 'Access denied.';
        unset($_SERVER['PHP_AUTH_PW']);
        unset($_SERVER['PHP_AUTH_USER']);
        exit;
    }
}

require 'vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Query;

$config = require 'config.php';
$client = new Client($config);

// Get all DHCP leases
$query = new Query('/ip/dhcp-server/lease/print');
$leases = $client->query($query)->read();

// Get current allowed address list entries
$query = new Query('/ip/firewall/address-list/print');
$query->where('list', 'allowed_internet');
$addressList = $client->query($query)->read();

$ips = array_column($leases, 'address');
array_multisort($ips, SORT_NATURAL, $leases);

// Build lookup of allowed IPs
$allowed = [];
foreach ($addressList as $entry) {
    if (isset($entry['address'])) {
        $allowed[$entry['address']] = $entry['.id'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>NetworkWhitelist Manager</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { padding: 8px 12px; border: 1px solid #ccc; text-align: left; }
        button { padding: 6px 10px; border: none; cursor: pointer; border-radius: 6px; }
        .disabled { background: #4caf50; color: white; }
        .active { background: #f44336; color: white; }
    </style>
    <script>
    async function toggleClient(ip, action) {
        let formData = new FormData();
        formData.append("ip", ip);
        formData.append("action", action);

        await fetch("toggle.php", { method: "POST", body: formData });
        location.reload();
    }
    </script>
</head>
<body>
    <h2>NetworkWhitelist Manager</h2>
    <table>
        <tr>
            <th>
                <h2>System Control</h2>
                <button id="systemToggle">Loading...</button>
            </th>
        </tr>
    </table>
    <br />
    <div id="clientTable">
        <h2>DHCP Clients</h2>
        <table>
            <tr>
                <th>Internet</th>
                <th>Host Name</th>
                <th>IP Address</th>
                <th>MAC Address</th>
                <th>Comment</th>
                <th>Status</th>
            </tr>
            <?php foreach ($leases as $lease): ?>
                <?php if (!isset($lease['address'])) continue; ?>
                <tr>
                    <td>
                        <?php $isAllowed = isset($allowed[$lease['address']]); ?>
                        <?php if ($isAllowed): ?>
                            <button class="disabled" onclick="toggleClient('<?= $lease['address'] ?>','disable')">Allowed</button>
                        <?php else: ?>
                            <button class="active" onclick="toggleClient('<?= $lease['address'] ?>','enable')">Blocked</button>
                        <?php endif; ?>
		    </td>
			<td>
    				<?php if (isset($lease['dynamic']) && $lease['dynamic'] === 'true'): ?>
        			<button class="makeStaticBtn" data-id="<?= $lease['.id'] ?>">Make Static</button>
    				<?php endif; ?>
    				<?= htmlspecialchars($lease['host-name'] ?? '') ?>
			</td>
                    <td><?= $lease['address'] ?></td>
                    <td><?= $lease['mac-address'] ?? '' ?></td>
			<td>
    				<input type="text" 
          				value="<?= htmlspecialchars($lease['comment'] ?? '') ?>" 
           				data-id="<?= $lease['.id'] ?? '' ?>" 
           				class="commentInput"
           				size="40">
			</td>    
		<td><?= $lease['status'] ?? '' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <script>
        async function loadSystemState() {
            let res = await fetch("system-toggle.php");
            let data = await res.json();
            let btn = document.getElementById("systemToggle");
            let table = document.getElementById("clientTable");

            if (data.error) {
                // rule not found → hide table
                table.style.display = "none";
                btn.textContent = "System Rule Missing";
                btn.className = "active";
                btn.disabled = true;
                return;
            }

            if (data.disabled) {
                // system disabled → hide table
                table.style.display = "none";
                btn.textContent = "System Disabled";
                btn.className = "active";
            } else {
                // system enabled → show table
                table.style.display = "block";
                btn.textContent = "System Active";
                btn.className = "disabled";
            }

            btn.onclick = async () => {
                let res2 = await fetch("system-toggle.php", { method: "POST" });
                let data2 = await res2.json();
                if (data2.disabled) {
                    table.style.display = "none";
                    btn.textContent = "System Disabled";
                    btn.className = "active";
                } else {
                    table.style.display = "block";
                    btn.textContent = "System Active";
                    btn.className = "disabled";
                }
            };
	      }

        document.querySelectorAll('.commentInput').forEach(input => {
            input.addEventListener('keypress', async (e) => {
                if (e.key === 'Enter') {
                    const leaseId = input.getAttribute('data-id');
                    const newComment = input.value;
        
                    let formData = new FormData();
                    formData.append('id', leaseId);
                    formData.append('comment', newComment);
        
                    try {
                        const res = await fetch('update-comment.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();
                        if (data.success) {
                            input.style.backgroundColor = '#d4edda'; // green flash
                            setTimeout(() => input.style.backgroundColor = '', 500);
                        } else {
                            input.style.backgroundColor = '#f8d7da'; // red flash
                            alert('Failed to update comment: ' + (data.error || 'Unknown error'));
                        }
                    } catch(err) {
                        input.style.backgroundColor = '#f8d7da';
                        console.error(err);
                    }
                }
            });
        });

        document.querySelectorAll('.makeStaticBtn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const leaseId = btn.getAttribute('data-id');
                let formData = new FormData();
                formData.append('id', leaseId);
        
                try {
                    let res = await fetch('make-static.php', {
                        method: 'POST',
                        body: formData
                    });
                    let data = await res.json();
                    if (data.success) {
                        btn.textContent = "Static";
                        btn.disabled = true;
                        btn.style.backgroundColor = "#d4edda"; // green flash
                    } else {
                        alert("Failed: " + (data.error || "Unknown error"));
                    }
                } catch (err) {
                    console.error(err);
                    alert("Error contacting server");
                }
            });
        });

        // load on page start
        loadSystemState();
    </script>
</body>
</html>
