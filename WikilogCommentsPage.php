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


/**
 * Wikilog comments namespace handler class.
 *
 * Displays a threaded discussion about a wikilog article, using its talk
 * page, replacing the mess that is the usual wiki talk pages. This allows
 * a simpler and faster interface for commenting on wikilog articles, more
 * like how traditional blogs work. It also allows other interesting things
 * that are difficult or impossible with usual talk pages, like counting the
 * number of comments for each post and generation of syndication feeds for
 * comments.
 *
 * @note This class was designed to integrate with Wikilog, and won't work
 * for the rest of the wiki. If you wan't a similar interface for the other
 * talk pages, you may want to check LiquidThreads or some other extension.
 */
class WikilogCommentsPage extends Article implements WikilogCustomAction {

	protected $mSkin;
	protected $mItem;
	protected $mFormOptions;
	protected $mUserCanPost;
	protected $mPostedComment;
	protected $mCaptchaForm;

	protected $mTrailing;

	/**
	 * Constructor.
	 *
	 * @param $title Title of the page.
	 * @param $wi WikilogInfo object with information about the wikilog and
	 *   the item.
	 */
	function __construct( Title &$title, WikilogInfo &$wi ) {
		global $wgUser, $wgRequest;

		parent::__construct( $title );
		wfLoadExtensionMessages( 'Wikilog' );

		$this->mSkin = $wgUser->getSkin();

		# Get item object relative to this comments page.
		$this->mItem = new WikilogItem( $wi );
		$this->mItem->loadData();

		# Check if user can post.
		$this->mUserCanPost = $wgUser->isAllowed( 'wikilog-post-comment' );

		# Form options.
		$this->mFormOptions = new FormOptions();
		$this->mFormOptions->add( 'wlAnonName', '' );
		$this->mFormOptions->add( 'wlComment', '' );
		$this->mFormOptions->fetchValuesFromRequest( $wgRequest,
			array( 'wlAnonName', 'wlComment' ) );

		# This flags if we are viewing a single comment (subpage).
		$this->mTrailing = $wi->getTrailing();
	}

	/**
	 * Handler for action=view requests.
	 */
	public function view() {
		global $wgOut, $wgRequest;

		$comment = NULL;
		$pid = false;

		if ( $this->mTrailing !== NULL ) {
			# Check if this is a comment.
			$comment = WikilogComment::newFromPageID( $this->mItem, $this->getID() );
			if ( $comment ) {
				$pid = $comment->mID;
			}
		}

		# Display talk page contents.
		parent::view();

		if ( $this->mItem->exists() ) {
			$header = Xml::tags( 'h2',
				array( 'id' => 'wl-comments-header' ),
				wfMsgExt( 'wikilog-comments', array( 'parseinline', 'content' ) )
			);
			$wgOut->addHtml( $header );

			# Display article comments.
			$replyTo = $wgRequest->getInt( 'wlParent', $pid );
			$wgOut->addHtml( $this->formatComments( $comment, $replyTo ) );

			# Display "post new comment" form, if appropriate.
			if ( $replyTo == $pid && $this->mUserCanPost ) {
				$wgOut->addHtml( $this->getPostCommentForm( $pid ) );
			}
		}
	}

	/**
	 * Handler for action=wikilog requests.
	 * Enabled via WikilogHooks::UnknownAction() hook handler.
	 */
	public function wikilog() {
		global $wgOut, $wgRequest;

		if ( $this->mItem->exists() && $this->isValidPost() ) {
			$this->mPostedComment = $this->getPostedComment();
			if ( $this->mPostedComment ) {
				if ( $wgRequest->getBool( 'wlActionCommentSubmit' ) ) {
					return $this->postComment( $this->mPostedComment );
				}
				if ( $wgRequest->getBool( 'wlActionCommentPreview' ) ) {
					return $this->view();
				}
			}
		}

		$wgOut->showErrorPage( 'nosuchaction', 'nosuchactiontext' );
	}

	/**
	 * Override Article::hasViewableContent() so that it doesn't return 404
	 * if the item page exists.
	 */
	public function hasViewableContent() {
		return parent::hasViewableContent() || $this->mItem->exists();
	}

	/**
	 * Formats wikilog article comments in a threaded format.
	 *
	 * @param $replyTo Comment ID to attach a reply form to.
	 */
	public function formatComments( $parent = NULL, $replyTo = false ) {
		global $wgOut;

		$comments = $this->mItem->getComments( $parent ? $parent->mThread : NULL );
		$top = count( $stack = array() );

		$html = '';

		foreach ( $comments as $comment ) {
			while ( $top > 0 && $comment->mParent != $stack[$top-1] ) {
				$html .= Xml::closeElement( 'div' );
				array_pop( $stack ); $top--;
			}

			$html .= Xml::openElement( 'div', array( 'class' => 'wl-thread' ) ).
				$this->formatComment( $comment );

			if ( $comment->mID == $replyTo && $this->mUserCanPost ) {
				$html .= Xml::wrapClass( $this->getPostCommentForm( $comment->mID ),
					'wl-thread', 'div' );
			}

			$top = array_push( $stack, $comment->mID );
		}

		while ( array_pop( $stack ) ) {
			$html .= Xml::closeElement( 'div' );
		}

		return $html;
	}

	/**
	 * Formats a single post in HTML.
	 */
	protected function formatComment( $comment ) {
		global $wgOut, $wgLang;

		$id = ( $comment->mID ? "c{$comment->mID}" : 'cpreview' );
		$link = $this->getCommentPermalink( $comment );
		$tools = $this->getCommentToolLinks( $comment );

		if ( $comment->mUserID ) {
			$by = wfMsgHtml( 'wikilog-comment-by',
				$this->mSkin->userLink( $comment->mUserID, $comment->mUserText ),
				$this->mSkin->userTalkLink( $comment->mUserID, $comment->mUserText )
			);
		} else {
			$by = wfMsgHtml( 'wikilog-comment-by-anon',
				$this->mSkin->userLink( $comment->mUserID, $comment->mUserText ),
				$this->mSkin->userTalkLink( $comment->mUserID, $comment->mUserText ),
				htmlspecialchars( $comment->mAnonName )
			);
		}

		$html = Xml::tags( 'div', array( 'class' => 'wl-comment-meta' ),
			"{$link} {$by} &nbsp; " .
			$wgLang->timeanddate( $comment->mTimestamp ) .
			" &nbsp; <small>{$tools}</small>"
		);

		$html .= Xml::tags( 'div', array( 'class' => 'wl-comment-text' ),
			$wgOut->parse( $comment->getText() )
		);

		return Xml::tags( 'div', array(
			'class' => 'wl-comment',
			'id' => $id
		), $html );
	}

	protected function getCommentPermalink( $comment ) {
		if ( $comment->mID ) {
			$title = clone $this->getTitle();
			$title->setFragment( "#c{$comment->mID}" );
			return $this->mSkin->link( $title, '#',
				array( 'title' => wfMsg( 'permalink' ) ) );
		} else {
			return '#';
		}
	}

	protected function getCommentToolLinks( $comment ) {
		if ( $comment->mID ) {
			$tools = array(
				$this->getCommentReplyLink( $comment ),
// 				$this->mSkin->link( $comment->mCommentTitle, 'page' ),
			);
			return wfMsg( 'wikilog-brackets',
				implode( wfMsg( 'comma-separator' ), $tools ) );
		} else {
			return '';
		}
	}

	protected function getCommentReplyLink( $comment ) {
		$title = clone $this->getTitle();
		$title->setFragment( "#c{$comment->mID}" );
		return $this->mSkin->link( $title, wfMsg( 'wikilog-reply-lc' ),
			array( 'title' => wfMsg( 'wikilog-reply-to-comment' ) ),
			array( 'wlParent' => $comment->mID ) );
	}

	/**
	 * Generates and returns a "post new comment" form for the user to fill in
	 * and submit.
	 *
	 * @param $parent If provided, generates a "post reply" form to reply to
	 *   the given comment.
	 */
	public function getPostCommentForm( $parent = NULL ) {
		global $wgUser, $wgTitle, $wgScript, $wgRequest;

		$comment = $this->mPostedComment;
		$opts = $this->mFormOptions;

		$preview = '';
		if ( $comment && $comment->mParent == $parent) {
			$check = $this->validateComment( $comment );
			if ( $check ) {
				$preview = Xml::wrapClass( wfMsg( $check ), 'mw-warning', 'div' );
			} else {
				$preview = $this->formatComment( $this->mPostedComment );
			}
			$header = wfMsgHtml( 'wikilog-form-preview' );
			$preview = "<b>{$header}</b>{$preview}<hr/>";
		}

		$form =
			Xml::hidden( 'title', $this->getTitle()->getPrefixedText() ).
			Xml::hidden( 'action', 'wikilog' ).
			Xml::hidden( 'wpEditToken', $wgUser->editToken() ).
			( $parent ? Xml::hidden( 'wlParent', $parent ) : '' );

		$fields = array();

		if ( $wgUser->isLoggedIn() ) {
			$fields[] = array(
				wfMsg( 'wikilog-form-name' ),
				$this->mSkin->userLink( $wgUser->getId(), $wgUser->getName() )
			);
		} else {
			$loginTitle = SpecialPage::getTitleFor( 'Userlogin' );
			$loginLink = $this->mSkin->makeKnownLinkObj( $loginTitle,
				wfMsgHtml( 'loginreqlink' ), 'returnto=' . $wgTitle->getPrefixedUrl() );
			$message = wfMsg( 'wikilog-posting-anonymously', $loginLink );
			$fields[] = array(
				Xml::label( wfMsg( 'wikilog-form-name' ), 'wl-name' ),
				Xml::input( 'wlAnonName', 25, $opts->consumeValue( 'wlAnonName' ),
					array( 'id' => 'wl-name', 'maxlength' => 255 ) ).
					"<p>{$message}</p>"
			);
		}

		$fields[] = array(
			Xml::label( wfMsg( 'wikilog-form-comment' ), 'wl-comment' ),
			Xml::textarea( 'wlComment', $opts->consumeValue( 'wlComment' ),
				40, 5, array( 'id' => 'wl-comment' ) )
		);

		if ( $this->mCaptchaForm ) {
			$fields[] = array( '', $this->mCaptchaForm );
		}

		$fields[] = array( '',
			Xml::submitbutton( wfMsg( 'wikilog-submit' ), array( 'name' => 'wlActionCommentSubmit' ) ) .'&nbsp;'.
			Xml::submitbutton( wfMsg( 'wikilog-preview' ), array( 'name' => 'wlActionCommentPreview' ) )
		);

		$form .= WikilogUtils::buildForm( $fields );

		foreach ( $opts->getUnconsumedValues() as $key => $value ) {
			$form .= Xml::hidden( $key, $value );
		}

		$form = Xml::tags( 'form', array(
			'action' => "{$wgScript}#wl-comment-form",
			'method' => 'post'
		), $form );

		$msgid = ( $parent ? 'wikilog-post-reply' : 'wikilog-post-comment' );
		return Xml::fieldset( wfMsg( $msgid ), $preview . $form,
			array( 'id' => 'wl-comment-form' ) ) . "\n";
	}

	/**
	 * Validates and saves a new comment. Redirects back to the comments page.
	 * @param $comment Posted comment.
	 */
	protected function postComment( WikilogComment &$comment ) {
		global $wgOut;

		$check = $this->validateComment( $comment );

		if ( $check !== false ) {
			return $this->view();
		}

		# Check through captcha.
		if ( !WlCaptcha::confirmEdit( $this->getTitle(), $comment->getText() ) ) {
			$this->mCaptchaForm = WlCaptcha::getCaptchaForm();
			$wgOut->addHtml( $this->getPostCommentForm( $comment->mParent ) );
			return;
		}

		if ( !$this->exists() ) {
			# Initialize a blank talk page.
			$user = User::newFromName( wfMsgForContent( 'wikilog-auto' ), false );
			$this->doEdit(
				wfMsgForContent( 'wikilog-newtalk-text' ),
				wfMsgForContent( 'wikilog-newtalk-summary' ),
				EDIT_NEW | EDIT_SUPPRESS_RC, false, $user
			);
		}

		$comment->saveComment();

		$dest = $this->getTitle();
		$dest->setFragment( "#c{$comment->mID}" );
		$wgOut->redirect( $dest->getFullUrl() );
	}

	/**
	 * Checks if the post data is correct and the user is allowed to post.
	 */
	protected static function isValidPost() {
		global $wgRequest, $wgUser;
		return $wgRequest->wasPosted()
			&& $wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' )
			&& $wgUser->isAllowed( 'wikilog-post-comment' ) );
	}

	/**
	 * Returns a new non-validated WikilogComment object with the contents
	 * posted using the post comment form. The result should be validated
	 * using validateComment() before using.
	 */
	protected function getPostedComment() {
		global $wgUser, $wgRequest;

		$parent = $wgRequest->getIntOrNull( 'wlParent' );
		$anonname = $wgRequest->getText( 'wlAnonName' );
		$text = $wgRequest->getText( 'wlComment' );

		$comment = WikilogComment::newFromText( $this->mItem, $text, $parent );
		$comment->setUser( $wgUser );
		if ( $wgUser->isAnon() ) {
			$comment->setAnon( $anonname );
		}
		return $comment;
	}

	/**
	 * Checks if the given comment is valid for posting.
	 * @param $comment Comment to validate.
	 * @returns False if comment is valid, error message identifier otherwise.
	 */
	protected static function validateComment( WikilogComment &$comment ) {
		global $wgWikilogMaxCommentSize;

		$length = strlen( $comment->mText );

		if ( $length == 0  ) {
			return 'wikilog-comment-is-empty';
		}
		if ( $length > $wgWikilogMaxCommentSize ) {
			return 'wikilog-comment-too-long';
		}

		if ( $comment->mUserID == 0 ) {
			$anonname = User::getCanonicalName( $comment->mAnonName, 'usable' );
			if ( !$anonname ) {
				return 'wikilog-comment-invalid-name';
			}
			$comment->setAnon( $anonname );
		}

		return false;
	}

}
