<?php
$db = new PDO('sqlite:' . __DIR__ . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// All remaining data for user_id=6 (Admin User)
$carts = $db->query("SELECT id, competition_id, status FROM enrolment_carts WHERE user_id=6")->fetchAll(PDO::FETCH_ASSOC);
echo "Carts: "; print_r($carts);

$enrolments = $db->query("SELECT id, cart_id, competition_id, status, deleted_at FROM enrolments WHERE competitor_profile_id=21")->fetchAll(PDO::FETCH_ASSOC);
echo "Enrolments: "; print_r($enrolments);
