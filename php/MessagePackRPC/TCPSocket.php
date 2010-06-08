<?php
class MessagePackRPC_TCPSocket
{
  const MILLISECONDS = 1000;
  
  public $addr = null;
  public $loop = null;
  public $tprt = null;  // Transport
  public $cltf = null;  // Packet send object
  
  public function __construct($addr, $loop, $tprt)
  {
    $this->messagePack = new MessagePack();
    $this->messagePack->initialize();

    $this->addr = $addr;
    $this->loop = $loop;
    $this->tprt = $tprt;
  }

  public function tryConnOpening()
  {
    // TODO: Event Loop Implementation
    if ($this->cltf != null) throw new Exception("already connected");
    $host       = $this->addr->getHost();
    $port       = $this->addr->getPort();
    $errs       = "";
    $errn       = "";
    $this->cltf = fsockopen($host, $port, $errn, $errs);

    if ($this->cltf != false) {
      // TODO: connection timeout 100ms
      stream_set_timeout($this->cltf, 0, 100 * self::MILLISECONDS);
      // TODO: block
      stream_set_blocking($this->cltf, 1);

      $this->cbConnectedFlg();
    } else {
      $this->cbFailed();
    }
  }
  
  protected function feof(&$time){
    $time = microtime(true);
    return feof($this->cltf);
  }
  protected function fwrite($sendmg){
    $msglen = strlen($sendmg);
    for($length = 0; $length < $msglen; ){
      $size = fwrite($this->cltf, substr($sendmg, $length));
      if(false === $size){
        return $length;
      }
      $length += $size;
    }
    return $length;
  }
  protected function fread($sizerp){
    $buf = '';
    $timer = null;  
    $timeout = 0.1;
    $start = microtime(true);
    while(!$this->feof($timer)){
      if($timeout < ($timer - $start)){
        break;
      }
      $read = fread($this->cltf, $sizerp);
      $buf .= $read;
    }
    return $buf;
  }

  public function tryMsgPackSend($sendmg = null, $sizerp = 1024)
  {
    // TODO: Event Loop Implementation
    // TODO: Socket Stream
    $flg = $this->fwrite($sendmg);
    //stream_socket_shutdown($this->cltf, STREAM_SHUT_WR);

    $buf = $this->fread($sizerp);
    //stream_socket_shutdown($this->cltf, STREAM_SHUT_RD);

    $this->tryConnClosing();

    if(!empty($buf)){
      $buf = msgpack_unpack($buf);
      $this->cbMsgsReceived($buf);
    }
  }

  public function tryConnClosing()
  {
    // TODO: Event Loop Implementation
    if ((!$this->cltf) && fclose($this->cltf)) {
      $this->cltf = null;
    }
  }

  public function cbConnectedFlg()
  {
    $this->tprt->cbConnectedFlg();
  }

  public function cbConnectFaile($reason = null)
  {
    $this->trySocketClose();
    $this->tprt->cbConnectFaile($reason);
  }

  public function cbMsgsReceived($buffer = null)
  {
    // TODO: Socket Stream
    $this->tprt->cbMsgsReceived($buffer);
  }

  public function cbClosed($reason = null)
  {
    $this->tryConnClosing();
    $this->tprt->cbClosed();
  }

  public function cbFailed($reason = null)
  {
    $this->tryConnClosing();
    $this->tprt->cbFailed();
  }
}

// TODO: Event Loop Implementation
// TODO: Event Loop Implementation
