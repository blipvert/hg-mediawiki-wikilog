<?php
/**
 * MediaWiki Wikilog extension
 * Copyright © 2008, 2009 Juliano F. Ravasi
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

	/**
	 * Alternate views.
	 */
	protected static $views = array( 'summary', 'archives' );

	function __construct( ) {
		parent::__construct( 'Wikilog' );
		wfLoadExtensionMessages('Wikilog');
	}

	public function getDefaultOptions() {
		global $wgWikilogNumArticles;

		$opts = new FormOptions();
		$opts->add( 'view',     'summary' );
		$opts->add( 'show',     'published' );
		$opts->add( 'wikilog',  '' );
		$opts->add( 'category', '' );
		$opts->add( 'author',   '' );
		$opts->add( 'tag',      '' );
		$opts->add( 'year',     '', FormOptions::INTNULL );
		$opts->add( 'month',    '', FormOptions::INTNULL );
		$opts->add( 'day',      '', FormOptions::INTNULL );
		$opts->add( 'limit',    $wgWikilogNumArticles );
		$opts->add( 'template', '' );
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
		$opts->fetchValuesFromRequest( $wgRequest, array( 'show', 'limit' ) );
		$opts->validateIntBounds( 'limit', 0, min( $wgFeedLimit, $wgWikilogSummaryLimit ) );
		return $opts;
	}

	public function execute( $parameters ) {
		global $wgRequest;

		$feedFormat = $wgRequest->getVal( 'feed' );

		if ( $feedFormat ) {
			$opts = $this->feedSetup();
			return $this->feedOutput( $feedFormat, $opts );
		} else {
			$opts = $this->webSetup( $parameters );
			return $this->webOutput( $opts );
		}
	}

	public function webOutput( FormOptions $opts ) {
		global $wgRequest, $wgOut, $wgMimeType, $wgTitle;
		global $wgWikilogNavTop, $wgWikilogNavBottom;

		# Set page title, html title, nofollow, noindex, etc...
		$this->setHeaders();
		$this->outputHeader();

		# Build query object.
		$query = self::getQuery( $opts );

		# If a wikilog is selected, set the title.
		if ( !$this->including() && ( $title = $query->getWikilogTitle() ) ) {
			# Retrieve wikilog front page
			$article = new Article( $title );
			$content = $article->getContent();
			$wgOut->setPageTitle( $title->getPrefixedText() );
			$wgOut->addWikiTextWithTitle( $content, $title );
		}

		# Display query options.
		if ( !$this->including() ) $this->doHeader( $opts );

		# Display the list of wikilog posts.
		if ( $opts['view'] == 'archives' ) {
			$pager = new WikilogArchivesPager( $query );
		} else if ( $opts['template'] ) {
			$t = Title::makeTitle( NS_TEMPLATE, $opts['template'] );
			$pager = new WikilogTemplatePager( $query, $t, $opts['limit'] );
		} else {
			$pager = new WikilogSummaryPager( $query, $opts['limit'] );
		}

		# Wikilog CSS wrapper class.
		$wgOut->addHTML( wfOpenElement( 'div', array( 'class' => 'wl-wrapper' ) ) );

		if ( $this->including() ) {
			/**
			 * NOTE: Wikilog needs to call the parser a few times in order to
			 * render the page. Some MediaWiki functions (like wfMsgExt() and
			 * wfMsgWikiHtml()) reset the parser when called, and this causes
			 * a lot of problems when Special:Wikilog is transcluded. Instead
			 * of working around each call that resets the parser, we replace
			 * the parser temporarily with a new blank instance.
			 *
			 * Unfortunately, we can't just clone $wgParser due to a possible
			 * bug in Parser::__destruct(), that damages data of the orignal
			 * parser object when the copy is destroyed.
			 */
			global $wgParser, $wgParserConf;
			$saved =& $wgParser;
			$class = $wgParserConf['class'];
			$wgParser = new $class( $wgParserConf ); // clone $wgParser;

			# Get pager body.
			$wgOut->addHTML( $pager->getBody() );

			# Restore saved parser.
			$wgParser =& $saved;
		} else {
			# Get pager body.
			$body = $pager->getBody();

			# Add navigation bars.
			if ( $wgWikilogNavTop ) $body = $pager->getNavigationBar() . $body;
			if ( $wgWikilogNavBottom ) $body = $body . $pager->getNavigationBar();

			# Output.
			$wgOut->addHTML( $body );
		}

		# Wikilog CSS wrapper class.
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
			if ( $alt != $opts['view'] ) {
				$altquery = wfArrayToCGI( array( 'view' => $alt ), $qarr );
				$wgOut->addLink( array(
					'rel' => 'alternate',
					'href' => $wgTitle->getLocalURL( $altquery ),
					'type' => $wgMimeType,
					'title' => wfMsgExt( "wikilog-view-{$alt}",
						array( 'content', 'parsemag' ) )
				) );
			}
		}
	}

	public function feedOutput( $format, FormOptions $opts ) {
		global $wgTitle;

		$feed = new WikilogFeed( $wgTitle, $format, self::getQuery( $opts ),
			$opts['limit'] );
		return $feed->execute();
	}

	public function parseInlineParams( $parameters, FormOptions $opts ) {
		global $wgWikilogNamespaces;

		if ( empty( $parameters ) ) return;

		foreach ( explode( ';', $parameters ) as $par ) {
			if ( is_numeric( $par ) ) {
				$opts['limit'] = intval( $par );
			} else if ( $par == 'all' || $par == 'published' || $par == 'drafts' ) {
				$opts['show'] = $par;
			} else if ( in_array( $par, self::$views ) ) {
				$opts['view'] = $par;
			} else if ( preg_match( '/^tag=(.+)$/', $par, $m ) ) {
				$opts['tag'] = $m[1];
			} else if ( preg_match( '/^date=(.+)$/', $par, $m ) ) {
				if ( ( $date = self::parseDateParam( $m[1] ) ) ) {
					list( $opts['year'], $opts['month'], $opts['day'] ) = $date;
				}
			} else {
				if ( ( $t = Title::newFromText( $par ) ) !== NULL ) {
					$ns = $t->getNamespace();
					if ( in_array( $ns, $wgWikilogNamespaces ) ) {
						$opts['wikilog'] = $t->getPrefixedDBkey();
					} else if ( $ns == NS_CATEGORY ) {
						$opts['category'] = $t->getDBkey();
					} else if ( $ns == NS_USER ) {
						$opts['author'] = $t->getDBkey();
					} else if ( $ns == NS_TEMPLATE ) {
						$opts['template'] = $t->getDBkey();
					}
				}
			}
		}
	}

	protected function doHeader( FormOptions $opts ) {
		global $wgScript, $wgOut;

		$out = Xml::hidden( 'title', $this->getTitle()->getPrefixedText() );

		$out .= self::queryForm( $opts );

		$unconsumed = $opts->getUnconsumedValues();
		foreach ( $unconsumed as $key => $value ) {
			$out .= Xml::hidden( $key, $value );
		}

		$form = Xml::tags( 'form', array( 'action' => $wgScript ), $out );

		$wgOut->addHTML(
			Xml::fieldset( wfMsg( 'wikilog-form-legend' ), $form,
				array( 'class' => 'wl-options' ) )
		);
	}

	protected static function queryForm( FormOptions $opts ) {
		global $wgContLang;

		$align = $wgContLang->isRtl() ? 'left' : 'right';
		$fields = self::getQueryFormFields( $opts );
		$columns = array_chunk( $fields, (count( $fields ) + 1) / 2, true );

		$out = Xml::openElement( 'table', array( 'width' => '100%' ) ) .
				Xml::openElement( 'tr' );

		foreach ( $columns as $fields ) {
			$out .= Xml::openElement( 'td' );
			$out .= Xml::openElement( 'table' );

			foreach ( $fields as $row ) {
				$out .= Xml::openElement( 'tr' );
				if ( is_array( $row ) ) {
					$out .= Xml::tags( 'td', array( 'align' => $align ), $row[0] );
					$out .= Xml::tags( 'td', NULL, $row[1] );
				} else {
					$out .= Xml::tags( 'td', array( 'colspan' => 2 ), $row );
				}
				$out .= Xml::closeElement( 'tr' );
			}

			$out .= Xml::closeElement( 'table' );
			$out .= Xml::closeElement( 'td' );
		}

		$out .= Xml::closeElement( 'tr' ) . Xml::closeElement( 'table' );
		return $out;
	}

	protected static function getQueryFormFields( FormOptions $opts ) {
		global $wgWikilogEnableTags;

		$fields = array();

		$fields['wikilog'] = Xml::inputLabelSep(
			wfMsg( 'wikilog-form-wikilog' ), 'wikilog', 'wl-wikilog', 25,
			$opts->consumeValue( 'wikilog' )
		);

		$fields['category'] = Xml::inputLabelSep(
			wfMsg( 'wikilog-form-category' ), 'category', 'wl-category', 25,
			$opts->consumeValue( 'category' )
		);

		$fields['author'] = Xml::inputLabelSep(
			wfMsg( 'wikilog-form-author' ), 'author', 'wl-author', 25,
			$opts->consumeValue( 'author' )
		);

		if ( $wgWikilogEnableTags ) {
			$fields['tag'] = Xml::inputLabelSep(
				wfMsg( 'wikilog-form-tag' ), 'tag', 'wl-tag', 25,
				$opts->consumeValue( 'tag' )
			);
		}

		$fields['date'] = array(
			Xml::label( wfMsg( 'wikilog-form-date' ), 'wl-month' ),
			Xml::monthSelector( $opts->consumeValue( 'month' ), '', 'wl-month' ) .
				" " . Xml::input( 'year', 4, $opts->consumeValue( 'year' ), array( 'maxlength' => 4 ) )
		);
		$opts->consumeValue( 'day' );	// ignore day, not really useful

		$statusSelect = new XmlSelect( 'show', 'wl-status', $opts->consumeValue( 'show' ) );
		$statusSelect->addOption( wfMsg( 'wikilog-show-all' ), 'all' );
		$statusSelect->addOption( wfMsg( 'wikilog-show-published' ), 'published' );
		$statusSelect->addOption( wfMsg( 'wikilog-show-drafts' ), 'drafts' );
		$fields['status'] = array(
			Xml::label( wfMsg( 'wikilog-form-status' ), 'wl-status' ),
			$statusSelect->getHTML()
		);

		$fields['submit'] = Xml::submitbutton( wfMsg( 'allpagessubmit' ) );
		return $fields;
	}

	public static function getQuery( $opts ) {
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

	public static function parseDateParam( $date ) {
		$m = array();
		if ( preg_match( '/^(\d+)(?:\/(\d+)(?:\/(\d+))?)?$/', $date, $m ) ) {
			return array(
				intval( $m[1] ),
				( isset( $m[2] ) ? intval( $m[2] ) : NULL ),
				( isset( $m[3] ) ? intval( $m[3] ) : NULL )
			);
		} else {
			return false;
		}
	}

}
