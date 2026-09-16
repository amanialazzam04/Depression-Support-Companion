<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
allow_cors();

$hasKey = (bool) cfg('OPENAI_API_KEY');
$model = cfg('OPENAI_MODEL', 'gpt-4o-mini');
json_out([
  'ok' => true,
  'hasApiKey' => $hasKey,
  'model' => $model,
]);
?>
