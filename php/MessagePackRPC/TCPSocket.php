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
    $this->cltf = stream_socket_client('tcp://' . $host .':' . $port, $errn, $errs, $timeout = 1, STREAM_CLIENT_CONNECT);

    if ($this->cltf != false) {
      // TODO: noblock
      stream_set_blocking($this->cltf, 0);

      $this->cbConnectedFlg();
    } else {
      $this->cbFailed();
    }
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
  
  public function tryMsgPackSend($sendmg = null, $sizerp = 1024)
  {
    while(true){
      $read = null;
      $write = array($this->cltf);
      $except = null;
      $w = stream_select($read, $write, $except, 0, 100 * self::MILLISECONDS);
      if($w === false){
        // TODO: error
        throw new RuntimeException('write stream');
      }
      if($w === 0){
        continue;
      }
      $this->fwrite($sendmg);
      break;
    }
    
    while(true){
      $read = array($this->cltf);
      $write = null;
      $except = null;
      $r = stream_select($read, $write, $except, 0, 100 * self::MILLISECONDS);
      if($r === false){
        // TODO: error
        throw new RuntimeException('read stream');
      }
      if(0 === $r){
        continue;
      }
      $buf = fread($this->cltf, $sizerp);
      break;
    }
    
    $this->tryConnClosing();

    $buf = msgpack_unpack($buf);
    $this->cbMsgsReceived($buf);
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
