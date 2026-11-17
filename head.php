<?php
// Shared <head> contents. Set $page_title before including.
require_once __DIR__ . '/helpers.php';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo h($page_title ?? 'eFootball League'); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@600;700;800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="styles/theme.css?v=1">
<script src="scripts/ui.js?v=1" defer></script>
