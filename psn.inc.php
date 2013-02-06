<?php

  // psn client for php » noorus 2013

  declare( encoding = 'UTF-8' );

  namespace PSN;

  mb_internal_encoding( 'UTF-8' );
  setlocale( LC_ALL, 'en_US.UTF-8' );

  require( 'util.quickxml.inc.php' );
  require( 'vendor/autoload.php' );

  define( 'PSN_Platform_PS3', 0 );

  define( 'PSN_Region_EU', 0 );
  define( 'PSN_Region_US', 1 );
  define( 'PSN_Region_JP', 2 );

  class Exception extends \Exception
  {
    public function __construct( $message = null, $code = 0, \Exception $previous = null )
    {
      parent::__construct( $message, $code, $previous );
    }
  }

  class User
  {
    protected $mJID = null;
    public function __construct( $jid )
    {
      $this->mJID = $jid;
    }
    public function getJID()
    {
      return $this->mJID;
    }
  }

  class Client
  {
    const Agent_Community   = 'PS3Community-agent/1.0.0 libhttp/1.0.0';
    const Agent_Application = 'PS3Application libhttp/3.5.5-000 (CellOS)';
    const URL_jidSearch     = 'http://searchjid.%s.np.community.playstation.net/basic_view/func/search_jid';
    const Host_Profile      = 'http://getprof.us.np.community.playstation.net';
    const Host_Trophy       = 'http://trophy.ww.np.community.playstation.net';
    const URL_UpdateList    = 'http://fus01.ps3.update.playstation.net/update/ps3/list/eu/ps3-updatelist.txt';
    protected $mClient = null;
    protected $mFirmware = null;
    protected $mPlatform = null;
    protected $mRealms = null;
    protected $mRegions = null;
    protected $mPlatforms = null;
    public function __construct( $platform = PSN_Platform_PS3 )
    {
      $this->mClient = new \Guzzle\Http\Client();
      $this->mPlatforms = array(
        PSN_Platform_PS3 => array( 'code' => 'ps3' )
      );
      if ( !isset( $this->mPlatforms[$platform] ) )
        throw new Exception( 'Invalid platform' );
      $this->mPlatform =& $this->mPlatforms[$platform];
      $this->mRegions = array(
        PSN_Region_EU => array( 'code' => 'eu' ),
        PSN_Region_US => array( 'code' => 'usa' ),
        PSN_Region_JP => array( 'code' => 'jp' )
      );
      $this->mRealms = array(
        'update'     => array(
          'agent'    => self::Agent_Application
        ),
        'community'  => array(
          'agent'    => self::Agent_Community,
          'user'     => 'c7y-basic01',
          'password' => 'A9QTbosh0W0D^{7467l-n_>2Y%JG^v>o'
        ),
        'trophy'     => array(
          'agent'    => self::Agent_Community,
          'user'     => 'c7y-trophy01',
          'password' => 'jhlWmT0|:0!nC:b:#x/uihx\'Y74b5Ycx'
        )
      );
      $this->findFirmware();
    }
    protected function setRequestRealm( $request, $realm )
    {
      if ( !isset( $this->mRealms[$realm] ) )
        throw new Exception( 'Invalid realm' );
      $request->addHeader( 'User-Agent', $this->mRealms[$realm]['agent'] );
      if ( isset( $this->mRealms[$realm]['user'] ) && isset( $this->mRealms[$realm]['password'] ) )
      {
        $request->setAuth(
          $this->mRealms[$realm]['user'],
          $this->mRealms[$realm]['password'],
          CURLAUTH_DIGEST
        );
      }
    }
    protected function findFirmware()
    {
      $request = $this->mClient->get( self::URL_UpdateList );
      $this->setRequestRealm( $request, 'update' );
      $response = $request->send();
      $body = (string)$response->getBody();
      $ret = preg_match( '/;SystemSoftwareVersion=(.+?);/', $body, $matches );
      if ( $ret == 1 && isset( $matches[1] ) && is_numeric( $matches[1] ) )
        $this->mFirmware = floatval( $matches[1] );
      else
        throw new Exception( 'Failed to find firmware version' );
    }
    protected function makeRegionURL( $region, $url )
    {
      if ( !isset( $this->mRegions[$region] ) )
        throw new Exception( 'Invalid region' );
      return sprintf( $url, $this->mRegions[$region]['code'] );
    }
    public function getFirmware()
    {
      return $this->mFirmware;
    }
    public function findJID( $psnID, $region )
    {
      $request = $this->mClient->post();
      $request->setUrl( $this->makeRegionURL( $region, self::URL_jidSearch ) );
      $this->setRequestRealm( $request, 'community' );
      $request->addHeader( 'Content-Type', 'text/xml; charset=UTF-8' );
      $xml = new \Util\QuickXML();
      $xml->start( 'searchjid' )->attribute( 'platform', $this->mPlatform['code'] )->attribute( 'sv', $this->mFirmware )->element( 'online-id', $psnID )->end();
      $request->setBody( $xml->done() );
      $response = $request->send();
      $result = simplexml_load_string( (string)$response->getBody() );
      var_dump( $result );
    }
    public function __destruct()
    {
      if ( $this->mClient )
        unset( $this->mClient );
    }
  }
