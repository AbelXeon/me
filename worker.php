<?php
// worker.php
set_time_limit(0);

echo "Worker started... Press Ctrl+C to stop.\n";

while (true) {
    // Run the cron logic
    include 'cron.php';
    
    echo "Waiting 60 seconds...\n";
    echo "--------------------------\n";
    sleep(60);
}