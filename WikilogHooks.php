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


/**
 * General wikilog hooks.
 */
class WikilogHooks {

	/**
	 * ArticleEditUpdatesDeleteFromRecentchanges hook handler function.
	 * Performs post-edit updates if article is a wikilog article.
	 */
	static function ArticleEditUpdates( &$article ) {
		$title = $article->getTitle();
		$wi = Wikilog::getWikilogInfo( $title );

		# Do nothing if not a wikilog article.
		if ( !$wi ) return true;

		if ( $title->isTalkPage() ) {
			# ::WikilogCommentsPage::
			# Invalidate cache of wikilog item page.
			if ( $wi->getItemTitle()->exists() ) {
				$wi->getItemTitle()->invalidateCache();
				$wi->getItemTitle()->purgeSquid();
			}
		} else if ( $wi->isItem() ) {
			# ::WikilogItemPage::
			$dbw = wfGetDB( DB_MASTER );
			$id = $article->getId();
			$editInfo = $article->mPreparedEdit;

			# Check if we have any wikilog metadata available.
			if ( isset( $editInfo->output->mExtWikilog ) ) {
				$output = $editInfo->output->mExtWikilog;

				# Update entry in wikilog_posts table.
				# Entries in wikilog_authors and wikilog_tags are updated
				# during LinksUpdate process.
				$updated = $dbw->timestamp();
				$pubdate = $output->mPublish ? $output->mPubDate : $updated;
				$dbw->replace(
					'wikilog_posts',
					'wlp_page',
					array(
						'wlp_page' => $id,
						'wlp_parent' => $wi->getTitle()->getArticleId(),
						'wlp_title' => $wi->getItemName(),
						'wlp_publish' => $output->mPublish,
						'wlp_pubdate' => $pubdate,
						'wlp_authors' => serialize( $output->mAuthors ),
						'wlp_tags' => serialize( $output->mTags ),
						'wlp_updated' => $updated
					),
					__METHOD__
				);
			} else {
				# Remove entry from tables. Entries in wikilog_authors and
				# wikilog_tags are removed during LinksUpdate process.
				$dbw->delete( 'wikilog_posts', array( 'wlp_page' => $id ), __METHOD__ );
			}

			# Invalidate cache of parent wikilog page.
			WikilogUtils::updateWikilog( $wi->getTitle() );
		} else {
			# ::WikilogMainPage::
			$dbw = wfGetDB( DB_MASTER );
			$id = $article->getId();
			$editInfo = $article->mPreparedEdit;

			# Check if we have any wikilog metadata available.
			if ( isset( $editInfo->output->mExtWikilog ) ) {
				$output = $editInfo->output->mExtWikilog;
				$subtitle = $output->mSummary
					? array( 'html', $output->mSummary )
					: '';

				# Update entry in wikilog_wikilogs table. Entries in
				# wikilog_authors and wikilog_tags are updated during
				# LinksUpdate process.
				$dbw->replace(
					'wikilog_wikilogs',
					'wlw_page',
					array(
						'wlw_page' => $id,
						'wlw_subtitle' => serialize( $subtitle ),
						'wlw_icon' => $output->mIcon ? $output->mIcon->getDBKey() : '',
						'wlw_logo' => $output->mLogo ? $output->mLogo->getDBKey() : '',
						'wlw_authors' => serialize( $output->mAuthors ),
						'wlw_updated' => $dbw->timestamp()
					),
					__METHOD__
				);
			} else {
				# Remove entry from tables. Entries in wikilog_authors and
				# wikilog_tags are removed during LinksUpdate process.
				$dbw->delete( 'wikilog_wikilogs', array( 'wlw_page' => $id ), __METHOD__ );
			}
		}

		return true;
	}

	/**
	 * ArticleDeleteComplete hook handler function.
	 * Purges wikilog metadata when an article is deleted.
	 * @note This function REQUIRES MediaWiki 1.13 or higher ($id parameter).
	 */
	static function ArticleDeleteComplete( &$article, &$user, $reason, $id ) {
		# Retrieve wikilog information.
		$wi = Wikilog::getWikilogInfo( $article->getTitle() );

		# Take special procedures if it is a wikilog page.
		if ( $wi ) {
			$dbw = wfGetDB( DB_MASTER );

			if ( $wi->isItem() ) {
				# Delete table entries.
				$dbw->delete( 'wikilog_posts',   array( 'wlp_page' => $id ) );
				$dbw->delete( 'wikilog_authors', array( 'wla_page' => $id ) );
				$dbw->delete( 'wikilog_tags',    array( 'wlt_page' => $id ) );

				# Invalidate cache of parent wikilog page.
				WikilogUtils::updateWikilog( $wi->getTitle() );
			} else {
				# Delete table entries.
				$dbw->delete( 'wikilog_wikilogs', array( 'wlw_page' => $id ) );
				$dbw->delete( 'wikilog_posts',    array( 'wlp_parent' => $id ) );
				$dbw->delete( 'wikilog_authors',  array( 'wla_page' => $id ) );
				$dbw->delete( 'wikilog_tags',     array( 'wlt_page' => $id ) );
			}
		}

		return true;
	}

	/**
	 * TitleMoveComplete hook handler function.
	 * Handles moving articles to and from wikilog namespaces.
	 */
	static function TitleMoveComplete( &$oldtitle, &$newtitle, &$user, $pageid, $redirid ) {
		global $wgWikilogNamespaces;

		$oldwl = in_array( ( $oldns = $oldtitle->getNamespace() ), $wgWikilogNamespaces );
		$newwl = in_array( ( $newns = $newtitle->getNamespace() ), $wgWikilogNamespaces );

		if ( $oldwl && $newwl ) {
			# Moving titles in wikilog namespaces.
			## Nothing to do.
			wfDebug( __METHOD__ . ": Moving title in wikilog namespaces ".
				"($oldns, $newns)." );
		} else if ( $oldwl ) {
			# Moving from wikilog namespace to normal namespace.
			# Purge wikilog data.
			wfDebug( __METHOD__ . ": Moving from wikilog namespace to other ".
				"namespace ($oldns, $newns). Purging wikilog data." );
			$dbw = wfGetDB( DB_MASTER );
			$dbw->delete( 'wikilog_wikilogs', array( 'wlw_page' => $pageid ) );
			$dbw->delete( 'wikilog_posts',    array( 'wlp_page' => $pageid ) );
			$dbw->delete( 'wikilog_posts',    array( 'wlp_parent' => $pageid ) );
			$dbw->delete( 'wikilog_authors',  array( 'wla_page' => $pageid ) );
			$dbw->delete( 'wikilog_tags',     array( 'wlt_page' => $pageid ) );
		} else if ( $newwl ) {
			# Moving from normal namespace to wikilog namespace.
			# Create wikilog data.
			wfDebug( __METHOD__ . ": Moving from other namespace to wikilog ".
				"namespace ($oldns, $newns). Creating wikilog data." );
		}
		return true;
	}

	/**
	 * LanguageGetSpecialPageAliases hook handler function.
	 * Adds language aliases for special pages.
	 */
	static function LanguageGetSpecialPageAliases( &$specialPageAliases, $lang ) {
		wfLoadExtensionMessages( 'Wikilog' );
		$title = Title::newFromText( wfMsg( 'wikilog-specialwikilog' ) );
		$specialPageAliases['SpecialWikilog'][] = $title->getDBKey();
		return true;
	}

	/**
	 * LanguageGetMagic hook handler function.
	 * Adds language aliases for magic words.
	 */
	static function LanguageGetMagic( &$magicWords, $lang ) {
		/// TODO: Language magic.
		$magicWords['wl-settings'] = array( 0, 'wl-settings' );
		$magicWords['wl-publish' ] = array( 0, 'wl-publish'  );
		$magicWords['wl-author'  ] = array( 0, 'wl-author'   );
		$magicWords['wl-tags'    ] = array( 0, 'wl-tags'     );
		return true;
	}

	/**
	 * LoadExtensionSchemaUpdates hook handler function.
	 * Updates wikilog database tables.
	 *
	 * @todo Add support for PostgreSQL and SQLite databases.
	 */
	static function ExtensionSchemaUpdates() {
		global $wgDBtype, $wgExtNewFields, $wgExtPGNewFields, $wgExtNewIndexes, $wgExtNewTables;

		$dir = dirname(__FILE__) . '/';
		if( $wgDBtype == 'mysql' ) {
			$wgExtNewTables[] = array( 'wikilog_wikilogs', $dir . 'wikilog-tables.sql' );
			$wgExtNewTables[] = array( 'wikilog_posts',    $dir . 'wikilog-tables.sql' );
			$wgExtNewTables[] = array( 'wikilog_authors',  $dir . 'wikilog-tables.sql' );
			$wgExtNewTables[] = array( 'wikilog_tags',     $dir . 'wikilog-tables.sql' );
			$wgExtNewFields[] = array( 'wikilog_posts', 'wlp_parent', $dir . 'archives/patch-post-titles.sql' );
			$wgExtNewFields[] = array( 'wikilog_posts', 'wlp_title',  $dir . 'archives/patch-post-titles.sql' );
			$wgExtNewFields[] = array( 'wikilog_wikilogs', 'wlw_authors', $dir . 'archives/patch-wikilog-authors.sql' );
		} else {
			/// TODO: PostgreSQL, SQLite, etc...
			print "\n".
				"Warning: There are no table structures for the Wikilog\n".
				"extension other than for MySQL at this moment.\n\n";
		}
		return true;
	}

	/**
	 * UnknownAction hook handler function.
	 * Handles action=wikilog requests.
	 */
	static function UnknownAction( $action, &$article ) {
		if ( $action == 'wikilog' && $article instanceof WikilogMainPage ) {
			$article->wikilog();
			return false;
		}
		return true;
	}

}
