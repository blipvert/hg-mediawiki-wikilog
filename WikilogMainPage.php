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


class WikilogMainPage extends Article {

	/**
	 * Alternate views.
	 */
	protected static $views = array( 'summary', 'archives' );

	/**
	 * Constructor.
	 */
	function __construct( &$title, &$wi ) {
		parent::__construct( $title );
		wfLoadExtensionMessages( 'Wikilog' );
	}

	/**
	 * View action handler.
	 */
	function view() {
		global $wgRequest, $wgOut, $wgMimeType;
		global $wgWikilogNavTop, $wgWikilogNavBottom;

		$query = new WikilogItemQuery( $this->mTitle );
		$query->setPubStatus( $wgRequest->getVal( 'show' ) );

		# RSS or Atom feed requested. Ignore all other options.
		if ( ( $feedFormat = $wgRequest->getVal( 'feed' ) ) ) {
			global $wgWikilogNumArticles;
			$feed = new WikilogFeed( $this->mTitle, $feedFormat, $query,
				$wgRequest->getInt( 'limit', $wgWikilogNumArticles ) );
			return $feed->execute();
		}

		# View selection.
		$view = $wgRequest->getVal( 'view', 'summary' );

		# Query filter options.
		$query->setCategory( $wgRequest->getVal( 'category' ) );
		$query->setAuthor( $wgRequest->getVal( 'author' ) );
		$query->setTag( $wgRequest->getVal( 'tag' ) );

		$year = $wgRequest->getIntOrNull( 'year' );
		$month = $wgRequest->getIntOrNull( 'month' );
		$day = $wgRequest->getIntOrNull( 'day' );
		$query->setDate( $year, $month, $day );

		# Display wiki text page contents.
		parent::view();

		# Create pager object, according to the type of listing.
		if ( $view == 'archives' ) {
			$pager = new WikilogArchivesPager( $query );
		} else {
			$pager = new WikilogSummaryPager( $query );
		}

		# Display list of wikilog posts.
		$body = $pager->getBody();
		if ( $wgWikilogNavTop ) $body = $pager->getNavigationBar() . $body;
		if ( $wgWikilogNavBottom ) $body = $body . $pager->getNavigationBar();
		$wgOut->addHTML( wfOpenElement( 'div', array( 'class' => 'wl-wrapper' ) ) );
		$wgOut->addHTML( $body );
		$wgOut->addHTML( wfCloseElement( 'div' ) );

		# Get query parameter array, for the following links.
		$qarr = $query->getDefaultQuery();
		
		# Add feed links.
		$wgOut->setSyndicated();
		if ( isset( $qarr['show'] ) ) {
			$altquery = wfArrayToCGI( array_intersect_key( $qarr, WikilogFeed::$paramWhitelist ) );
			$wgOut->setFeedAppendQuery( $altquery );
		}

		# Add links for alternate views.
		foreach ( self::$views as $alt ) {
			if ( $alt != $view ) {
				$altquery = wfArrayToCGI( array( 'view' => $alt ), $qarr );
				$wgOut->addLink( array(
					'rel' => 'alternate',
					'href' => $this->mTitle->getLocalURL( $altquery ),
					'type' => $wgMimeType,
					'title' => wfMsgExt( "wikilog-view-{$alt}",
						array( 'content', 'parsemag' ) )
				) );
			}
		}
	}
}

