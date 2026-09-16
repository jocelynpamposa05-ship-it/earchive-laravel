<?php
// This script rebuilds the search index from the database and also writes a local index file for fallback search.
session_start();

// Security Check: Ensure only admin can run this
if (!isset($_SESSION['id']) || $_SESSION['role'] != 'admin') {
    die("Access Denied: Admins only.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Search Indexer</title><style>body{font-family:'Segoe UI', sans-serif;padding:40px;text-align:center;background:#f1f3f6;}</style></head>
<body>
<?php

echo "<h1>Search Indexer</h1>";

include "db_conn.php";

$indexFile = __DIR__ . '/uploads/search_index.json';
$indexDir = dirname($indexFile);
if (!is_dir($indexDir)) {
    mkdir($indexDir, 0777, true);
}

$appId = 'WGL9LUXGKD';
$adminApiKey = '7dea79ab13a168dbe7c73fa8d58e48b5';
$indexName = 'research_papers';
$canIndex = false;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
    if (class_exists('Algolia\\AlgoliaSearch\\SearchClient') && $appId !== 'WGL9LUXGKD') {
        $canIndex = true;
    }
}

try {
    echo "<h2>Starting to index 'research_papers'...</h2>";

    $query = "SELECT id, title, author, department, subject, publish_year, keywords FROM research_papers WHERE is_deleted = 0";
    $result = @mysqli_query($conn, $query);

    if (!$result) {
        $error = mysqli_error($conn);
        if (strpos($error, "Unknown column") !== false) {
            throw new Exception("Database column missing. Please run <a href='fix_db.php'>fix_db.php</a> first.<br>Details: " . $error);
        }
        throw new Exception("Database query failed: " . $error);
    }

    $records = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['objectID'] = (string)$row['id'];
        $records[] = $row;
    }

    if (empty($records)) {
        echo "<p>No records found in the database to index.</p>";
        exit;
    }

    file_put_contents($indexFile, json_encode($records, JSON_PRETTY_PRINT));

    if ($canIndex) {
        $client = \Algolia\AlgoliaSearch\SearchClient::create($appId, $adminApiKey);
        $index = $client->initIndex($indexName);
        $index->clearObjects();
        $index->saveObjects($records);
        echo "<h3 style='color:green;'>✅ Success! Indexed " . count($records) . " records to the '$indexName' index in Algolia and saved a local fallback index.</h3>";
    } else {
        echo "<h3 style='color:green;'>✅ Success! Rebuilt the local search index for " . count($records) . " papers.</h3>";
    }

} catch (Throwable $e) {
    echo "<h2 style='color:red;'>An error occurred:</h2><pre>" . $e->getMessage() . "</pre>";
}
?>
<br><br>
<a href="dashboardadmin.php" style="background:#004d00;color:white;padding:12px 25px;text-decoration:none;border-radius:8px;font-weight:bold;">&larr; Back to Dashboard</a>
</body></html>