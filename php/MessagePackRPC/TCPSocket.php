<?php
class MessagePackRPC_TCPSocket
{
  const MILLISECONDS = 1000;
  const USECONDS = 1;
  
  public $addr = null;
  public $loop = null;
  public $tprt = null;  // Transport
  public $cltf = null;  // Packet send object
  
  public function __construct($addr, $loop, $tprt)
  {
    //$this->messagePack = new MessagePack();
    //$this->messagePack->initialize();

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
    $this->cltf = stream_socket_client('tcp://' . $host . ':' . $port, $errn, $errs);

    if ($this->cltf != false) {
      // non blocking mode
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
    // TODO: Event Loop Implementation
    // TODO: Socket Stream
    
    while(true){
      $read = null;
      $write = array($this->cltf);
      $except = null;
      $w = stream_select($read, $write, $except, 0, 100 * self::USECONDS);
      if($w === false){
        // TODO: error
        throw new RuntimeException('write stream');
      }
      if($w === 0){
        continue;
      }
      // TODO: stream write
      $this->fwrite($sendmg);
      break;
    }
    
    $unpacker = new MessagePack;
    $unpacker->initialize();
    $buffer = '';
    while(true){
      $read = array($this->cltf);
      $write = null;
      $except = null;
      $r = stream_select($read, $write, $except, 0, 20000);
      if($r === false){
        // TODO: error
        throw new RuntimeException('read stream');
      }
      if($r === 0){
        continue;
      }
      
      $buffer .= fread($this->cltf, $sizerp);
      $nread = $unpacker->execute($buffer, $nread);
      if($unpacker->finished()){
        break;
      }
    }
    
    $this->tryConnClosing();

    $data = $unpacker->data();
    $this->cbMsgsReceived($data);
  }

  public function tryConnClosing()
  {
    // TODO: Event Loop Implementation
    if ((!$this->cltf) && @flose($this->cltf)) {
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
