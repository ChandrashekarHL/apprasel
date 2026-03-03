# Troubleshooting cURL Connection Issues

The error `Failed to connect to erp.gmit.info port 443 after 21015 ms: Couldn't connect to server` indicates that the server hosting the appraisal system cannot reach the main ERP server.

## Possible Causes

1.  **NAT Loopback Issue**: If both the appraisal system and the ERP system are hosted on the same physical server (or behind the same router), trying to access the external public IP/domain from inside the network might fail.
2.  **Firewall Blocking**: The server's firewall might be blocking outgoing connections to port 443.
3.  **DNS Resolution**: The server might be resolving `erp.gmit.info` to an unreachable IP.
4.  **IP Allowlisting**: The ERP server might have IP restrictions.

## Diagnostic Steps

Run the `test_connection.php` script I created to diagnose the issue.

Visit: `https://apprasel.gmit.info/test_connection.php` (or wherever your app is hosted).

## Solutions

### Solution A: Use Localhost (If on the same server)

If both apps are on the same server, modify the API URL in `sync_all_departments.php` and `sync_missing_staff.php` to use `localhost` or `127.0.0.1`.

**Change:**
```php
$apiUrl = "https://erp.gmit.info/v3/fms/get_staff_by_dept.php";
```

**To:**
```php
// Use localhost if on same server to bypass external network routing
$apiUrl = "http://127.0.0.1/v3/fms/get_staff_by_dept.php"; 
// OR
$apiUrl = "http://localhost/v3/fms/get_staff_by_dept.php";
```
*Note: You may need to use `http` instead of `https` for localhost connections.*

### Solution B: Add Host Entry

Edit the `/etc/hosts` file (Linux) or `C:\Windows\System32\drivers\etc\hosts` (Windows) on the server to point the domain to the local IP.

```
127.0.0.1  erp.gmit.info
```
This forces the server to resolve the domain to itself without going out to the internet.

### Solution C: Increase Timeout

If the connection is just slow, you can increase the timeout in the PHP script, but >20 seconds is usually a network failure, not just slowness.

In `sync_all_departments.php`:
```php
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
```

### Solution D: Check Firewall

Ensure the server allows **outgoing** traffic on port 443. Check `iptables` or cloud security groups (AWS Security Groups, DigitalOcean Firewalls, etc.).
