<?php

  // psn client for php » noorus 2013

  declare( encoding = 'UTF-8' );

  namespace PSN;

  mb_internal_encoding( 'UTF-8' );
  setlocale( LC_ALL, 'en_US.UTF-8' );
  libxml_use_internal_errors( true );

  require( 'vendor/autoload.php' );

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
    protected $mOwner = null;
    protected $mJID = null;
    protected $mHasProfile = false;
    protected $mName = null;
    protected $mCountry = null;
    protected $mAboutMe = null;
    protected $mAvatar = null;
    public function __construct( $owner, $jid )
    {
      if ( !( $owner instanceof Client ) )
        throw new Exception( 'Owner is not an instance of client' );
      $this->mOwner = $owner;
      $this->mJID = $jid;
      $this->_getProfile();
      $this->_getTrophies();
    }
    protected function _getTrophies()
    {
      $xml = $this->mOwner->_executeTrophiesRequest( $this->mJID );
    }
    protected function _getProfile()
    {
      $xml = $this->mOwner->_executeProfileRequest( $this->mJID );
      if ( isset( $xml['result'] ) )
      {
        $result = hexdec( (string)$xml['result'] );
        switch ( $result )
        {
          case 0x0D: // not found
            $this->mHasProfile = false;
          break;
          case 0x00: // found
            $this->mHasProfile = true;
            if ( isset( $xml->onlinename ) && mb_strlen( (string)$xml->onlinename ) > 0 )
              $this->mName = (string)$xml->onlinename;
            if ( isset( $xml->country ) && mb_strlen( (string)$xml->country ) > 0 )
              $this->mCountry = (string)$xml->country;
            if ( isset( $xml->aboutme ) && mb_strlen( (string)$xml->aboutme ) > 0 )
              $this->mAboutMe = (string)$xml->aboutme;
            if ( isset( $xml->avatarurl ) )
            {
              $avatar = array();
              if ( isset( $xml->avatarurl['id'] ) )
                $avatar['id'] = (int)$xml->avatarurl['id'];
              if ( mb_strlen( (string)$xml->avatarurl ) > 0 )
                $avatar['url'] = (string)$xml->avatarurl;
              $this->mAvatar = $avatar;
            }
          break;
          default:
            throw new Exception( 'Unknown return code for profile search request: '.$result );
          break;
        }
      } else
        throw new Exception( 'Cannot parse XML from profile search request' );
    }
    public function getJID() { return $this->mJID; }
    public function hasProfile() { return $this->mHasProfile; }
    public function getName() { return $this->mName; }
    public function getCountry() { return $this->mCountry; }
    public function getAboutMe() { return $this->mAboutMe; }
    public function getAvatar() { return $this->mAvatar; }
  }

  class Client
  {
    const Platform          = 'ps3';
    const Agent_Community   = 'PS3Community-agent/1.0.0 libhttp/1.0.0';
    const Agent_Application = 'PS3Application libhttp/3.5.5-000 (CellOS)';
    const URL_jidSearch     = 'http://searchjid.%s.np.community.playstation.net/basic_view/func/search_jid';
    const URL_getProfile    = 'http://getprof.us.np.community.playstation.net/basic_view/func/get_profile';
    const URL_getTrophies   = 'http://trophy.ww.np.community.playstation.net/trophy/func/get_user_info';
    const URL_UpdateList    = 'http://fus01.ps3.update.playstation.net/update/ps3/list/eu/ps3-updatelist.txt';
    protected $mClient = null;
    protected $mFirmware = null;
    protected $mRealms = null;
    protected $mRegions = null;
    public function __construct()
    {
      $this->mClient = new \Guzzle\Http\Client();
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
    public function _executeProfileRequest( $jid )
    {
      $request = $this->mClient->post();
      $request->setUrl( self::URL_getProfile );
      $this->setRequestRealm( $request, 'community' );
      $request->addHeader( 'Content-Type', 'text/xml; charset=UTF-8' );
      $xml = new \Util\XMLOut();
      $xml->start( 'profile' )->attribute( 'platform', self::Platform )->attribute( 'sv', $this->mFirmware )->element( 'jid', $jid )->end();
      $request->setBody( $xml->done() );
      $response = $request->send();
      $returnXml = simplexml_load_string( (string)$response->getBody() );
      if ( $returnXml === false )
        throw new Exception( 'Invalid XML returned for profile search request' );
      return $returnXml;
    }
    public function _executeTrophiesRequest( $jid )
    {
      $request = $this->mClient->post();
      $request->setUrl( self::URL_getTrophies );
      $this->setRequestRealm( $request, 'community' );
      $request->addHeader( 'Content-Type', 'text/xml; charset=UTF-8' );
      $xml = new \Util\XMLOut();
      $xml->start( 'nptrophy' )->attribute( 'platform', self::Platform )->attribute( 'sv', $this->mFirmware );
      $xml->element( 'jid', $jid );
      $xml->end();
      $request->setBody( $xml->done() );
      $response = $request->send();
      // var_dump( (string)$response->getMessage() );
      $returnXml = simplexml_load_string( (string)$response->getBody() );
      if ( $returnXml === false )
        throw new Exception( 'Invalid XML returned for trophy search request' );
      return $returnXml;
    }
    public function _executeIDResolveRequest( $psnID, $region )
    {
      $request = $this->mClient->post();
      $request->setUrl( $this->makeRegionURL( $region, self::URL_jidSearch ) );
      $this->setRequestRealm( $request, 'community' );
      $request->addHeader( 'Content-Type', 'text/xml; charset=UTF-8' );
      $xml = new \Util\QuickXML();
      $xml->start( 'searchjid' )->attribute( 'platform', self::Platform )->attribute( 'sv', $this->mFirmware )->element( 'online-id', $psnID )->end();
      $request->setBody( $xml->done() );
      $response = $request->send();
      $returnXml = simplexml_load_string( (string)$response->getBody() );
      if ( $returnXml === false )
        throw new Exception( 'Invalid XML returned for JID search request' );
      return $returnXml;
    }
    public function findUsersByName( $psnID, $region )
    {
      $xml = $this->_executeIDResolveRequest( $psnID, $region );
      if ( isset( $xml['result'] ) )
      {
        $result = hexdec( (string)$xml['result'] );
        switch ( $result )
        {
          case 0x1B: // nothing found
            return array();
          break;
          case 0x00: // found something
            $users = array();
            foreach ( $xml->jid as $jid )
            {
              $user = new User( $this, (string)$jid );
              $users[] = $user;
            }
            return $users;
          break;
          default: // unknown - error out
            throw new Exception( 'JID search returned unknown result code '.$result );
          break;
        }
      } else
        throw new Exception( 'Cannot parse XML from JID search request' );
    }
    public function __destruct()
    {
      if ( $this->mClient )
        unset( $this->mClient );
    }
  }
