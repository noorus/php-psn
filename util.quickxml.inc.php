<?php

  // util.quickxml » noorus 2013

  declare( encoding = 'UTF-8' );

  namespace Util;

  class QuickXML
  {
    protected $_xml = null;
    private $_resolve = array( 'start' => 'startElement', 'end' => 'endElement', 'attribute' => 'writeAttribute', 'element' => 'writeElement' );
    public function __construct()
    {
      $this->_xml = new \XMLWriter();
      $this->_xml->openMemory();
      $this->_xml->setIndent( false );
      $this->_xml->startDocument( '1.0', 'utf-8' );
    }
    public function __call( $method, $args )
    {
      if ( isset( $this->_resolve[$method] ) )
        $method = $this->_resolve[$method];
      if ( !call_user_func_array( array( $this->_xml, $method ), $args ) )
        throw new Exception( 'XMLWriter call failed: '.$method );
      return $this;
    }
    public function done()
    {
      $this->_xml->endDocument();
      $ret = $this->_xml->outputMemory( true );
      unset( $this );
      return $ret;
    }
  }
