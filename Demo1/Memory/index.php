<?php
  // Allocate 50MB of memory
  $mem = str_repeat("a", 1024 * 1024 * 50);
  echo "OK! Allocated 50MB.";
  // Keep the memory allocated for 120 seconds
  sleep(120);
?>