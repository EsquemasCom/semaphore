<?php
class Semaphore
{
	private $State;
	private $Key;
	private $Wait;
	private $Expire;


	public function __construct($Key, $Wait = 10, $Expire = 3600)
	{
		$this->Key = 'Semaphore_'.$Key;
		$this->Wait = $Wait;
		$this->Expire = $Expire;
		$this->State = 0;
	}


	public function On()
	{
		if ($this->State == 1) return 1;
		$Elapsed = 0;
		$SleepTime = 0.050; //segundos, en este caso 50ms.
		while ($Elapsed <= $this->Wait) {
			MemCachedAdd ($this->Key, "0", $this->Expire, true);
			$this->State = MemCachedIncr ($this->Key, true);
			if ($this->State == 1)
				return 1;
			$Elapsed += $SleepTime;
			usleep ($SleepTime*1000000);
		}
		return 0;
	}


	function __destruct() {
		$this->Off();
	}


	public function Off()
	{
		if ($this->State == 1) {
			$Delete = MemCachedDelete ($this->Key);
			$this->State = 0;
			if ($Delete !== false)
				return 1;
		}
		return 0;
	}
} //class Semaphore


function Semaphore_Test ()
{
	define('MEMCACHED_HOST', 'localhost');
	define('MEMCACHED_PORT', '11211');
	$S1 = new Semaphore ("Test");
	if ($S1->On()) {
		echo "OK: Semaforo S1 adquirido.\n";
		$S2 = new Semaphore("Test");
		if ($S2->On()) {
			echo "ERROR: NO DEBERIA ADQUIRIRSE S2->ON()!!!!\n";
		} else {
			echo "OK: Semaforo S2 no adquirido.\n";
		}
	} else {
		echo "WARN: Semaforo S1 NO adquirido, hay otro proceso simultaneo???\n";
	}
	unset($S1);
	$S3 = new Semaphore ("Test");
	if ($S3->On()) {
		echo "OK: Semaforo S3 adquirido.\n";
	} else {
		echo "WARN: Semaforo S3 NO adquirido, hay otro pcoeso simultaneo???\n";
	}
}

//Semaphore_Test ();


function GetMemCachedObj()
{
	static $MemCachedObj;
	if (!$MemCachedObj) {
		$MemCachedObj = new Memcached();
		if (!$MemCachedObj->addServer(MEMCACHED_HOST, MEMCACHED_PORT)) throw new Exception
			('No se pudo conectar al Memcached: '.$MemCachedObj->getResultMessage().'.');
		$MemCachedObj->setOption (Memcached::OPT_COMPRESSION, false);
		$MemCachedObj->setOption (Memcached::OPT_BINARY_PROTOCOL, false);
		$MemCachedObj->setOption (Memcached::OPT_CONNECT_TIMEOUT, 10000);
	}
	return $MemCachedObj;
}


function MemCachedDelete($Key, $NoException=false)
{
	$MemCachedObj = GetMemCachedObj();
	$RetVal = $MemCachedObj->delete($Key);
	if ($RetVal === false && $NoException == false) throw new Exception (
		'Memcached: error al eliminar clave "'.$Key.'": '.$MemCachedObj->getResultMessage().'.');
	return $RetVal;
}


function MemCachedIncr($Key, $NoException=false)
{
	$MemCachedObj = GetMemCachedObj();
	$RetVal = $MemCachedObj->increment($Key);
	if ($RetVal === false && $NoException == false) throw new Exception(
		'Memcached: error al incrementar clave "'.$Key.'": '.$MemCachedObj->getResultMessage().'.');
	return $RetVal;
}


function MemCachedAdd($Key, $Value, $Time, $NoException=false)
{
	$MemCachedObj = GetMemCachedObj();
	$RetVal = $MemCachedObj->add($Key, $Value, $Time);
	if ($RetVal === false && $NoException == false) throw new Exception(
		'MemCached: error al agregar clave "'.$Key.'": '.$MemCachedObj->getResultMessage().'.');
	return $RetVal;
}

?>
