<?php

namespace Destiny\Service;

use Destiny\Service;
use Destiny\Config;
use Destiny\MimeType;
use Destiny\HttpApiConsumer;
use Destiny\Utils\String;
use Destiny\Utils\Date;
use Destiny\Utils\Lol;
use Destiny\Utils\Http;
use Destiny\AppException;

class LeagueApiService extends Service {
	
	protected static $instance = null;

	/**
	 * Singleton
	 * 
	 * @return LeagueApiService
	 */
	public static function instance() {
		return parent::instance ();
	}

	public function getStatus(array $options = array()) {
		return new HttpApiConsumer ( array_merge ( array (
				'url' => Config::$a ['lolapi'] ['url'],
				'contentType' => MimeType::JSON,
				'onfetch' => function ($json) {
					if (false == $json ['success'] && $json ['data'] != null) {
						throw new AppException ( 'LoL API down.' );
					}
					return $json ['data'];
				} 
		), $options ) );
	}

	public function getLeague(array $summoner) {
		$playerLeague = new HttpApiConsumer ( array (
				'url' => new String ( Config::$a ['lolapi'] ['url'] . '{summoner.region}/{summoner.name}/league?key={apikey}', array (
						'summoner.region' => $summoner ['region'],
						'summoner.name' => utf8_decode ( $summoner ['name'] ),
						'apikey' => Config::$a ['lolapi'] ['apikey'] 
				) ),
				'contentType' => MimeType::JSON,
				'params' => $summoner,
				'onfetch' => function ($league, $summoner) {
					if (false == $league ['success']) {
						throw new AppException ( 'LoL API down.' );
					}
					$league ['data'] ['rankInt'] = Lol::rankToInt ( $league ['data'] ['rank'] );
					return $league ['data'];
				} 
		) );
		return $playerLeague->getResponse ();
	}

	public function getRecentGames(array $summoner, $limit = 10) {
		$games = new HttpApiConsumer ( array (
				'url' => new String ( Config::$a ['lolapi'] ['url'] . '{summoner.region}/{summoner.name}/games?key={apikey}&limit={limit}', array (
						'summoner.region' => $summoner ['region'],
						'summoner.name' => utf8_decode ( $summoner ['name'] ),
						'apikey' => Config::$a ['lolapi'] ['apikey'],
						'limit' => $limit 
				) ),
				'contentType' => MimeType::JSON 
		) );
		return $games->getResponse ();
	}

	public function getInGameProgress(array $summoner) {
		$progress = new HttpApiConsumer ( array (
				'url' => new String ( Config::$a ['lolapi'] ['url'] . '{summoner.region}/{summoner.name}/ingame?key={apikey}', array (
						'summoner.region' => $summoner ['region'],
						'summoner.name' => utf8_decode ( $summoner ['internalName'] ),
						'apikey' => Config::$a ['lolapi'] ['apikey'] 
				) ),
				'contentType' => MimeType::JSON 
		) );
		return $progress->getResponse ();
	}

	public function getSummoner(array $summoner) {
		$playerSummoner = new HttpApiConsumer ( array (
				'url' => new String ( Config::$a ['lolapi'] ['url'] . '{summoner.region}/{summoner.name}?key={apikey}', array (
						'summoner.region' => $summoner ['region'],
						'summoner.name' => utf8_decode ( $summoner ['name'] ),
						'apikey' => Config::$a ['lolapi'] ['apikey'] 
				) ),
				'contentType' => MimeType::JSON,
				'onfetch' => function ($json) {
					if (false == $json ['success']) {
						throw new AppException ( 'LoL API down.' );
					}
					$json ['data'] ['revisionDate'] = Date::getDateTime ( floatval ( $json ['data'] ['revisionDate'] ) / 1000, Date::FORMAT );
					return $json;
				} 
		) );
		$data = $playerSummoner->getResponse ();
		if ($data == null || $data ['success'] == false) {
			$data = $summoner;
		} else {
			$data = $data ['data'];
		}
		$data ['id'] = $summoner ['id'];
		$data ['region'] = Lol::getRegion ( $summoner ['region'] );
		return $data;
	}

	public function getSummoners(array $options = array()) {
		$lookupSummoners = Config::$a ['lol'] ['summoners'];
		$summoners = array ();
		foreach ( $lookupSummoners as $info ) {
			if (! $info ['public'])
				continue;
			$summoner = $this->getSummoner ( $info );
			$summoner ['region'] = Lol::getRegion ( $info ['region'] );
			$summoner ['league'] = ($info ['stats']) ? $this->getLeague ( $info ) : null;
			$summoners [] = $summoner;
		}
		// Put the most up to date summoner in from of the other
		usort ( $summoners, function ($a, $b) {
			if (! isset ( $a ['revisionDate'] )) {
				return true;
			}
			if (! isset ( $b ['revisionDate]'] )) {
				return false;
			}
			return (strtotime ( $a ['revisionDate'] ) < strtotime ( $b ['revisionDate'] ));
		} );
		$response = $summoners;
		return $response;
	}

}