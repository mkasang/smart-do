<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me    = auth();
$pdo   = get_pdo();
$uid   = $me['id'];
$today = date('Y-m-d');

$lStmt = $pdo->prepare("SELECT COUNT(*) AS t, SUM(status='active') AS a, SUM(status='completed') AS c, SUM(type='simple') AS s, SUM(type='checklist') AS ch FROM lists WHERE user_id=?");
$lStmt->execute([$uid]);
$l = $lStmt->fetch();

$iStmt = $pdo->prepare("SELECT COUNT(*) AS t, SUM(i.is_done) AS d FROM list_items i JOIN lists l ON l.id=i.list_id WHERE l.user_id=?");
$iStmt->execute([$uid]);
$i = $iStmt->fetch();

$cStmt = $pdo->prepare("SELECT SUM(due_date < ? AND status!='completed') AS over, SUM(due_date=?) AS today, SUM(due_date > ?) AS up FROM lists WHERE user_id=? AND due_date IS NOT NULL");
$cStmt->execute([$today, $today, $today, $uid]);
$cal = $cStmt->fetch();

$sOut = $pdo->prepare("SELECT COUNT(DISTINCT list_id) FROM shared_lists WHERE owner_id=?");
$sOut->execute([$uid]);
$sIn = $pdo->prepare("SELECT COUNT(*) FROM shared_lists WHERE shared_with_user_id=?");
$sIn->execute([$uid]);

success([
    'lists'   => ['total'=>(int)$l['t'],'active'=>(int)$l['a'],'completed'=>(int)$l['c'],'simple'=>(int)$l['s'],'checklist'=>(int)$l['ch']],
    'items'   => ['total'=>(int)$i['t'],'completed'=>(int)$i['d'],'pending'=>(int)$i['t']-(int)$i['d'],'rate'=>$i['t']>0?round($i['d']/$i['t']*100,1):0],
    'calendar'=> ['overdue'=>(int)$cal['over'],'due_today'=>(int)$cal['today'],'upcoming'=>(int)$cal['up']],
    'sharing' => ['shared_by_me'=>(int)$sOut->fetchColumn(),'shared_with_me'=>(int)$sIn->fetchColumn()],
]);
