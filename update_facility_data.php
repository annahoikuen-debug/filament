<?php
$db = new PDO('sqlite:database/database.sqlite');
$stmt = $db->prepare('UPDATE facilities SET bank = :bank, billing = :billing WHERE id = 1');
$bank = json_encode(['name' => '〇〇銀行', 'branch_name' => '〇〇支店', 'account_type' => '普通', 'account_number' => '1234567', 'account_holder' => 'カ）ヒマワリケア']);
$billing = json_encode(['direct_debit_day' => 27, 'bank_transfer_due_days' => 30]);
$stmt->execute(['bank' => $bank, 'billing' => $billing]);
echo 'Updated facility with proper bank and billing data';
?>