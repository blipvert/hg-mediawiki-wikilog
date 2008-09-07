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

class WikilogMainPage extends Article {

	function __construct( &$title, &$wi ) {
		parent::__construct( $title );
		wfLoadExtensionMessages( 'Wikilog' );
	}

	function view() {
		global $wgRequest, $wgOut;

		$query = new WikilogItemQuery( $this->mTitle );
		$query->setPubStatus( $wgRequest->getVal( 'show' ) ); # published, drafts, all

		# RSS or Atom feed requested. Ignore all other options.
		if ( ( $feedType = $wgRequest->getVal( 'feed' ) ) ) {
			global $wgWikilogNumArticles;
			$feed = new WikilogFeed( $this->mTitle, $query );
			$limit = $wgRequest->getInt( 'limit', $wgWikilogNumArticles );
			return $feed->feed( $feedType, $limit );
		}

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
		if ( $wgRequest->getVal( 'list' ) == 'archives' ) {
			$pager = new WikilogArchivesPager( $query );
		} else {
			$pager = new WikilogSummaryPager( $query );
		}

		# Display list of wikilog posts.
		$wgOut->addHTML( wfOpenElement( 'div', array( 'class' => 'wl-wrapper' ) ) );
		$wgOut->addHTML( $pager->getNavigationBar() );
		$wgOut->addHTML( $pager->getBody() );
		$wgOut->addHTML( $pager->getNavigationBar() );
		$wgOut->addHTML( wfCloseElement( 'div' ) );

		# Add feed links.
		$wgOut->setSyndicated();
	}
}

