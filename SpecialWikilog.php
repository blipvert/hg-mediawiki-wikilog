<?php
/**
 * MediaWiki Wikilog extension
 * Copyright © 2008 Juliano F. Ravasi < dev at juliano info >
 * http://juliano.info/en/Projects/MediaWiki_Wikilog
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
 *
 * Adds blogging features to MediaWiki, through a special namespace,
 * making it a wiki-blog hybrid, like a Bliki.
 */

if ( !defined( 'MEDIAWIKI' ) )
	die();

class SpecialWikilog extends IncludableSpecialPage {

	function __construct( ) {
		parent::__construct( 'Wikilog' );
		wfLoadExtensionMessages('Wikilog');
	}

	function execute( $paramstr ) {
		global $wgUser, $wgRequest, $wgOut;
		global $wgWikilogNamespaces;

		$query = new WikilogItemQuery();
		$query->setPubStatus( $wgRequest->getVal( 'show' ) );

		# RSS or Atom feed requested. Ignore all other options.
		if ( ( $feedType = $wgRequest->getVal( 'feed' ) ) ) {
			global $wgWikilogNumArticles, $wgTitle;
			$feed = new WikilogFeed( $wgTitle, $query );
			$limit = $wgRequest->getInt( 'limit', $wgWikilogNumArticles );
			return $feed->feed( $feedType, $limit );
		}

		# Set page title, html title, nofollow, noindex, etc...
		$this->setHeaders();

		# Check if user is allowed to display this page.
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return;
		}

		# Process parameters and options.
		list( $titles, $params ) = SpecialWikilog::parseParameters( $paramstr );
		$wikilogTitle = null;
		$list = 'summary';

		foreach ( $titles as $title ) {
			if ( ( $t = Title::newFromText( $title ) ) !== null ) {
				if ( in_array( $t->getNamespace(), $wgWikilogNamespaces ) ) {
					$query->setWikilogTitle( $t );
					$wikilogTitle = $t;
				} else if ( $t->getNamespace() == NS_CATEGORY ) {
					$query->setCategory( $t );
				} else if ( $t->getNamespace() == NS_USER ) {
					$query->setAuthor( $t );
				}
			}
		}

		foreach ( $params as $key => $value ) {
			switch ( $key ) {
			case 'list':
				$list = $wgRequest->getVal( 'list', $value );
				break;

			case 'show':
				$query->setPubStatus( $wgRequest->getVal( 'show', $value ) );
				break;

			case 'tag':
				$query->setTag( $wgRequest->getVal( 'tag', $value ) );
				break;

			case 'date':
				$date = explode( '/', $value );
				$year  = $wgRequest->getInt( 'year',  isset( $date[0] ) ? intval( $date[0] ) : null );
				$month = $wgRequest->getInt( 'month', isset( $date[1] ) ? intval( $date[1] ) : null );
				$year  = $wgRequest->getInt( 'year',  isset( $date[2] ) ? intval( $date[2] ) : null );
				$query->setDate( $year, $month, $day );
				break;
			}
		}

		# Output special page summary 'wikilog-summary'.
		$this->outputHeader();

		# If a wikilog is selected, set the title.
		if ( $wikilogTitle !== null ) {
			global $wgParser;

			# Retrieve wikilog front page
			$article = new Article( $wikilogTitle );
			$content = $article->getContent();
			$wgOut->setPageTitle( $wikilogTitle->getPrefixedText() );
			$wgOut->addWikiTextWithTitle( $content, $wikilogTitle );
		}

		# Display list of wikilog posts
		if ( $list == 'archives' ) {
			$pager = new WikilogArchivesPager( $query );
		} else {
			$pager = new WikilogSummaryPager( $query );
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


	static function parseParameters( $str ) {
		$titles = array();
		$params = array();
		if ( !empty( $str ) ) {
			foreach ( explode( ';', $str ) as $c ) {
				if ( preg_match( '/^~([^:=]*)(?:[:=](.*))?/', $c, $m ) ) {
					$params[$m[1]] = isset( $m[2] ) ? $m[2] : true;
				} else {
					$titles[] = $c;
				}
			}
		}
		return array( $titles, $params );
	}
}
