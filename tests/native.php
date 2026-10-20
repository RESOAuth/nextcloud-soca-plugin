<?php
declare(strict_types=1);
// Run only in the disposable Nextcloud demo, never against production accounts.
require '/var/www/html/lib/base.php';
require __DIR__ . '/../vendor/autoload.php';
use OCP\Server;
use OCA\Soca\{Service,Store};
use RESOAuth\SocaHost\Identity;

if (getenv('SOCA_NATIVE_TESTS') !== '1') { throw new RuntimeException('Set SOCA_NATIVE_TESTS=1 for a disposable host.'); }
$store = new Store(Server::get(\OCP\IDBConnection::class),Server::get(\OCP\ISession::class),Server::get(\OCP\IConfig::class),Server::get(\Psr\Log\LoggerInterface::class));
$users=Server::get(\OCP\IUserManager::class);$groups=Server::get(\OCP\IGroupManager::class);
$service=new Service($store,Server::get(\OCP\IConfig::class),$users,$groups,Server::get(\OCP\IUserSession::class));
$count=0;
function nativeCheck(bool $ok,string $name):void{global $count;++$count;if(!$ok){throw new RuntimeException($name);}}
function nativeDeny(callable $fn,string $name):void{try{$fn();}catch(Throwable){nativeCheck(true,$name);return;}nativeCheck(false,$name);}
$group=$groups->get('soca-users')??$groups->createGroup('soca-users');
$uid='soca_test_'.bin2hex(random_bytes(6));$sub='native-'.bin2hex(random_bytes(8));$hash=Identity::hash($service->config()['issuer'],$sub);$user=null;$provisioned=null;
$configPath=tempnam('/tmp','soca-native-');$original=getenv('SOCA_CONFIG_FILE');
try {
 $user=$users->createUser($uid,base64_encode(random_bytes(64)));$group->addUser($user);$user->setEMailAddress('same-address@example.com');
 nativeDeny(fn()=>$service->account(['sub'=>$sub,'email'=>'same-address@example.com','email_verified'=>true]),'Email must never select existing account');
 $store->link($hash,$uid);nativeCheck($service->account(['sub'=>$sub])->getUID()===$uid,'Explicit identity link resolves native account');
 nativeDeny(fn()=>$store->link($hash,$uid),'Unique link database constraint');
 $user->setEnabled(false);nativeDeny(fn()=>$service->account(['sub'=>$sub]),'Disabled native user is denied');$user->setEnabled(true);
 nativeDeny(fn()=>$service->eligible('localadmin'),'Local administrators are denied SSO');
 $state=hash('sha256',random_bytes(32));$store->putTicket($state,'browser','revision',time()+300);
 nativeCheck(!$store->consumeTicket($state,'different-browser','revision',time()),'Copied callback cannot consume native ticket');
 nativeCheck($store->consumeTicket($state,'browser','revision',time()),'Native ticket is consumed');nativeCheck(!$store->consumeTicket($state,'browser','revision',time()),'Native callback replay is rejected');
 $deployment=json_decode(file_get_contents($original),true,32,JSON_THROW_ON_ERROR);$deployment['provision']=true;file_put_contents($configPath,json_encode($deployment,JSON_THROW_ON_ERROR));putenv('SOCA_CONFIG_FILE='.$configPath);
 $newSub='provision-'.bin2hex(random_bytes(8));$newHash=Identity::hash($service->config()['issuer'],$newSub);
 $provisioned=$service->account(['sub'=>$newSub,'name'=>'Native fixture user','email'=>'native-'.bin2hex(random_bytes(4)).'@example.com','email_verified'=>true]);
 nativeCheck($groups->isInGroup($provisioned->getUID(),'soca-users')&&!$groups->isAdmin($provisioned->getUID()),'Opt-in native provisioning grants only configured group');
 nativeCheck($service->account(['sub'=>$newSub])->getUID()===$provisioned->getUID(),'Returning provisioned account keeps stable link');
 $store->unlink($newHash);$provisioned->delete();$provisioned=null;
 $collisionSub='collision-'.bin2hex(random_bytes(8));$collisionHash=Identity::hash($service->config()['issuer'],$collisionSub);$collisionUid=Identity::generatedUid($collisionHash);$collision=$users->createUser($collisionUid,base64_encode(random_bytes(64)));
 try{nativeDeny(fn()=>$service->account(['sub'=>$collisionSub]),'Pre-existing generated username is not linked');nativeCheck($store->linkedUid($collisionHash)===null,'Collision leaves no identity link');}finally{$collision->delete();}
 $store->unlink($hash);nativeCheck($users->get($uid)!==null,'Unlink preserves native account');
 echo "Nextcloud native host: $count assertions passed. No provider login simulated at host level.\n";
} finally {putenv('SOCA_CONFIG_FILE='.$original);$store->unlink($hash);if($user!==null){$user->delete();}if($provisioned!==null){$provisioned->delete();}unlink($configPath);}
