<?php
/**
 * MediaWiki Wikilog extension
 * Copyright © 2008 Juliano F. Ravasi
 * http://www.mediawiki.org/wiki/Extension:Wikilog
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

/**
 * @addtogroup Extensions
 * @author Juliano F. Ravasi < dev juliano info >
 */

if ( !defined( 'MEDIAWIKI' ) )
	die();


class SpecialWikilog extends IncludableSpecialPage {

	function __construct( ) {
		parent::__construct( 'Wikilog' );
		wfLoadExtensionMessages('Wikilog');
	}

	public function getDefaultOptions() {
		global $wgWikilogNumArticles;

		$opts = new FormOptions();
		$opts->add( 'list',     'summary' );
		$opts->add( 'show',     'published' );
		$opts->add( 'wikilog',  '' );
		$opts->add( 'category', '' );
		$opts->add( 'author',   '' );
		$opts->add( 'tag',      '' );
		$opts->add( 'year',     '', FormOptions::INTNULL );
		$opts->add( 'month',    '', FormOptions::INTNULL );
		$opts->add( 'day',      '', FormOptions::INTNULL );
		$opts->add( 'limit',    $wgWikilogNumArticles );
		return $opts;
	}

	public function webSetup( $parameters ) {
		global $wgRequest, $wgWikilogSummaryLimit;

		$opts = $this->getDefaultOptions();
		$opts->fetchValuesFromRequest( $wgRequest );

		# Collect inline parameters, they have precedence over query params.
		$this->parseInlineParams( $parameters, $opts );

		$opts->validateIntBounds( 'limit', 0, $wgWikilogSummaryLimit );
		return $opts;
	}

	public function feedSetup() {
		global $wgRequest, $wgFeedLimit, $wgWikilogSummaryLimit;

		$opts = $this->getDefaultOptions();
		$opts->fetchValuesFromRequest( $wgRequest, array( 'limit' ) );
		$opts->validateIntBounds( 'limit', 0, min( $wgFeedLimit, $wgWikilogSummaryLimit ) );
		return $opts;
	}

	public function execute( $parameters ) {
		global $wgRequest;

		$feedType = $wgRequest->getVal( 'feed' );

		if ( $feedType ) {
			$opts = $this->feedSetup();
			return $this->feedOutput( $feedType, $opts );
		} else {
			$opts = $this->webSetup( $parameters );
			return $this->webOutput( $opts );
		}
	}

	public function webOutput( $opts ) {
		global $wgRequest, $wgOut;

		$query = self::getQuery( $opts );

		# Set page title, html title, nofollow, noindex, etc...
		$this->setHeaders();
		$this->outputHeader();

		# If a wikilog is selected, set the title.
		if ( !$this->including() && ( $title = $query->getWikilogTitle() ) ) {
			# Retrieve wikilog front page
			$article = new Article( $title );
			$content = $article->getContent();
			$wgOut->setPageTitle( $title->getPrefixedText() );
			$wgOut->addWikiTextWithTitle( $content, $title );
		}

// 		$wgOut->addHTML( Xml::element( 'pre', null, var_export( $query, 1 ) ) );

		# Display list of wikilog posts
		if ( $opts['list'] == 'archives' ) {
			$pager = new WikilogArchivesPager( $query );
		} else {
			$pager = new WikilogSummaryPager( $query, $opts['limit'] );
		}

		$wgOut->addHTML( wfOpenElement( 'div', array( 'class' => 'wl-wrapper' ) ) );
		if ( $this->including() ) {
			$wgOut->addHTML( $pager->getBody() );
		} else {
			$wgOut->addHTML( $pager->getNavigationBar() );
			$wgOut->addHTML( $pager->getBody() );
			$wgOut->addHTML( $pager->getNavigationBar() );
		}
		$wgOut->addHTML( wfCloseElement( 'div' ) );

		# Add feed links.
		$wgOut->setSyndicated();
	}

	public function feedOutput( $feedType, $opts ) {
		global $wgTitle;

		$feed = new WikilogFeed( $wgTitle, self::getQuery( $opts ) );
		return $feed->feed( $feedType, $opts['limit'] );
	}

	public function parseInlineParams( $parameters, $opts ) {
		global $wgWikilogNamespaces;

		if ( empty( $parameters ) ) return;

		foreach ( explode( ';', $parameters ) as $par ) {
			if ( is_numeric( $par ) ) {
				$opts['limit'] = intval( $par );
			} else if ( $par == 'all' || $par == 'published' || $par == 'drafts' ) {
				$opts['show'] = $par;
			} else if ( $par == 'summary' || $par == 'archives' ) {
				$opts['list'] = $par;
			} else if ( preg_match( '/^tag=(.+)$/', $par, $m ) ) {
				$opts['tag'] = $m[1];
			} else if ( preg_match( '/^date=(.+)$/', $par, $m ) ) {
				if ( ( $date = self::parseDateParam( $m[1] ) ) ) {
					list( $opts['year'], $opts['month'], $opts['day'] ) = $date;
				}
			} else {
				if ( ( $t = Title::newFromText( $par ) ) !== null ) {
					if ( in_array( $t->getNamespace(), $wgWikilogNamespaces ) ) {
						$opts['wikilog'] = $t->getPrefixedDBkey();
					} else if ( $t->getNamespace() == NS_CATEGORY ) {
						$opts['category'] = $t->getDBkey();
					} else if ( $t->getNamespace() == NS_USER ) {
						$opts['author'] = $t->getDBkey();
					}
				}
			}
		}
	}

	static public function getQuery( $opts ) {
		$query = new WikilogItemQuery();
		$query->setPubStatus( $opts['show'] );
		if ( ( $t = $opts['wikilog'] ) ) {
			$query->setWikilogTitle( Title::newFromText( $t ) );
		}
		if ( ( $t = $opts['category'] ) ) {
			$query->setCategory( $t );
		}
		if ( ( $t = $opts['author'] ) ) {
			$query->setAuthor( $t );
		}
		if ( ( $t = $opts['tag'] ) ) {
			$query->setTag( $t );
		}
		$query->setDate( $opts['year'], $opts['month'], $opts['day'] );
		return $query;
	}

	static public function parseDateParam( $date ) {
		$m = array();
		if ( preg_match( '/^(\d+)(?:\/(\d+)(?:\/(\d+))?)?$/', $date, $m ) ) {
			return array(
				intval( $m[1] ),
				( isset( $m[2] ) ? intval( $m[2] ) : null ),
				( isset( $m[3] ) ? intval( $m[3] ) : null )
			);
		} else {
			return false;
		}
	}

}
