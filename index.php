<?php
/**
 * Redirect users to donation.php
 *
 * This script performs an HTTP redirect to the donation form located at donation.php.
 * It should be placed in the root directory and will be the default landing page.
 *
 * @author Ralph Göstenmeier
 * @version 1.0
 */

// Specify the target page for redirection
$targetPage = 'donation.php';

// Choose the type of redirection:
//  - 301: Permanent Redirect (use when the resource has moved permanently)
//  - 302: Temporary Redirect (use when the resource has moved temporarily)
// For most cases, 302 is appropriate unless you intend the redirection to be permanent.
$redirectStatus = 302;

// Perform the redirection
header("Location: $targetPage", true, $redirectStatus);

exit;
