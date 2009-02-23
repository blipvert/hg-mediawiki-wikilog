<?php
/**
 * Internationalisation file for extension Wikilog.
 *
 * @addtogroup Extensions
 */

$messages = array();

/** English
 * @author Juliano F. Ravasi
 */
$messages['en'] = array(
	# Extension information
	'wikilog-desc'				=> 'Adds blogging features, creating a wiki-blog hybrid.',
	'wikilog-auto'				=> 'Wikilog Auto',	# reserved username
	'right-wikilog-post-comment'=> 'Post comments to wikilog articles',

	# Special:Wikilog
	'wikilog'					=> 'Wikilogs',	# Page title
	'wikilog-specialwikilog'	=> 'Wikilog',	# Special page name

	# Wikilog tab
	'wikilog-tab'				=> 'Wikilog',
	'wikilog-tab-title'			=> 'Wikilog actions',
	'wikilog-information'		=> 'Wikilog information',
	'wikilog-post-count-published'	=>
		'There are $1 published {{PLURAL:$1|article|articles}} in this wikilog,',
	'wikilog-post-count-drafts'	=>
		'plus $1 unpublished (draft) {{PLURAL:$1|article|articles}},',
	'wikilog-post-count-all'	=>
		'for a total of $1 {{PLURAL:$1|article|articles}}.',
	'wikilog-new-item'			=> 'Create new wikilog article',
	'wikilog-new-item-go'		=> 'Create',
	'wikilog-item-name'			=> 'Article name:',

	# Generic strings
	'wikilog-published'			=> 'Published',
	'wikilog-updated'			=> 'Updated',
	'wikilog-draft'				=> 'Draft',
	'wikilog-authors'			=> 'Authors',
	'wikilog-wikilog'			=> 'Wikilog',
	'wikilog-title'				=> 'Title',
	'wikilog-actions'			=> 'Actions',
	'wikilog-comments'			=> 'Comments',
	'wikilog-view-archives'		=> 'Archives',
	'wikilog-view-summary'		=> 'Summary',
	'wikilog-draft-title-mark'	=> '(draft)',
	'wikilog-anonymous-mark'	=> '(anonymous)',

	# Pager strings
	'wikilog-pager-newer-n'		=> '← newer $1',
	'wikilog-pager-older-n'		=> 'older $1 →',
	'wikilog-pager-newest'		=> '←← newest',
	'wikilog-pager-oldest'		=> 'oldest →→',
	'wikilog-pager-prev'		=> '← previous',
	'wikilog-pager-next'		=> 'next →',
	'wikilog-pager-first'		=> '←← first',
	'wikilog-pager-last'		=> 'last →→',
	'wikilog-pager-empty'		=>
		'<div class="wl-empty">(no items)</div>',

	# Comments page link text
	'wikilog-no-comments'		=> 'no comments',
	'wikilog-has-comments'		=> '{{PLURAL:$1|one comment|$1 comments}}',

	'wikilog-author-signature'	=> '[[{{ns:User}}:$1|$1]] ([[{{ns:User_talk}}:$1|talk]])',
	'wikilog-item-brief-header'	=> ': <i><small>by $5, from [[$1|$2]], $6.</small></i>',
	'wikilog-item-brief-footer'	=> '',
	'wikilog-item-more'			=> '[[$3|&rarr; continue reading...]]',
	'wikilog-item-sub'			=> '',
	'wikilog-item-header'		=> '',
	'wikilog-item-footer'		=> ': <i>&mdash; $5 &#8226; $6 &#8226; $7</i>',

	# Comments
	'wikilog-comment-by'		=> 'Comment by $1 ($2)',
	'wikilog-comment-by-anon'	=> 'Comment by $3 (anonymous)',
	'wikilog-reply-to-comment'	=> 'Post a reply to this comment',
	'wikilog-newtalk-text'		=> '<!-- blank page created by Wikilog -->',
	'wikilog-newtalk-summary'	=> 'created automatically by wikilog',

	# Atom and RSS feeds
	'wikilog-feed-title'		=> '{{SITENAME}} - $1 [$2]', # $1 = title, $2 = content language
	'wikilog-feed-description'	=> 'Read the most recent posts in this feed.',

	# Warning and error messages
	'wikilog-error-msg'			=> 'Wikilog: $1',
	'wikilog-error-title'		=> 'Wikilog error',
	'wikilog-invalid-param'		=> 'Invalid parameter: $1.',
	'wikilog-invalid-author'	=> 'Invalid author: $1.',
	'wikilog-invalid-date'		=> 'Invalid date: $1.',
	'wikilog-invalid-tag'		=> 'Invalid tag: $1.',
	'wikilog-invalid-file'		=> 'Invalid file: $1.',
	'wikilog-file-not-found'	=> 'Non-existing file: $1.',
	'wikilog-not-an-image'		=> 'File is not an image: $1.',
	'wikilog-out-of-context'	=>
			'Warning: Wikilog tags are being used out of context. ' .
			'They should only be used in articles in the Wikilog namespace.',
	'wikilog-too-many-authors'	=>
			'Warning: Too many authors listed in this wikilog post.',
	'wikilog-too-many-tags'		=>
			'Warning: Too many tags listed in this wikilog post.',
	'wikilog-comment-is-empty'	=>
			'Posted comment is blank.',
	'wikilog-comment-too-long'	=>
			'Posted comment is too long.',
	'wikilog-comment-invalid-name' =>
			'Provided name is invalid.',

	'wikilog-reading-draft'		=>
			'<div class="mw-warning">'.
			'<p>This wikilog article is a draft, it was not published yet.</p>'.
			'</div>',

	'wikilog-posting-anonymously' =>	# $1 = "login" link
			'You are currently not logged in; your comment will be posted '.
			'anonymously, identified by your Internet connection address. '.
			'You should either provide a pseudonym above to identify your '.
			'comment or $1 for it to be properly attributed.',

	# Forms
	'wikilog-post-comment'		=> 'Post a new comment',
	'wikilog-post-reply'		=> 'Post a new reply',
	'wikilog-form-legend'		=> 'Search for wikilog posts',
	'wikilog-form-wikilog'		=> 'Wikilog:',
	'wikilog-form-category'		=> 'Category:',
	'wikilog-form-name'			=> 'Name:',
	'wikilog-form-author'		=> 'Author:',
	'wikilog-form-tag'			=> 'Tag:',
	'wikilog-form-date'			=> 'Date:',
	'wikilog-form-status'		=> 'Status:',
	'wikilog-form-preview'		=> 'Preview:',
	'wikilog-form-comment'		=> 'Comment:',
	'wikilog-show-all'			=> 'All posts',
	'wikilog-show-published'	=> 'Published',
	'wikilog-show-drafts'		=> 'Drafts',
	'wikilog-submit'			=> 'Submit',
	'wikilog-preview'			=> 'Preview',	# verb
	'wikilog-reply-lc'			=> 'reply',		# verb

	# Untranslatable strings
	'wikilog-summary'			=> '',			# Special page summary
	'wikilog-backlink'			=> '← $1',
	'wikilog-brackets'			=> '[$1]',
	'wikilog-navigation-bar'	=>
		'<div class="wl-navbar visualClear">'.
		'<div style="float:left">$1 • $2</div>'.
		'<div style="float:right">$3 • $4</div>'.
		'<div style="text-align:center">$5</div>'.
		'</div>',
);

/** Portuguese (Português)
 * @author Juliano F. Ravasi
 */
$messages['pt'] = array(
	# Extension information
	'wikilog-desc'				=> 'Adiciona recursos de blog, criando um híbrido wiki-blog.',
	'wikilog-auto'				=> 'Wikilog Auto',	# reserved username
	'right-wikilog-post-comment'=> 'Postar comentários em artigos wikilog',

	# Special:Wikilog
	'wikilog'					=> 'Wikilogs',
	'wikilog-specialwikilog'	=> 'Wikilog',

	# Wikilog tab
	'wikilog-tab'				=> 'Wikilog',
	'wikilog-tab-title'			=> 'Ações wikilog',
	'wikilog-information'		=> 'Informações do wikilog',
	'wikilog-post-count-published'	=>
		'Há $1 {{PLURAL:$1|artigo publicado|artigos publicados}} neste wikilog,',
	'wikilog-post-count-drafts'	=>
		'mais $1 {{PLURAL:$1|artigo não-publicado (rascunho)|$1 artigos não-publicados (rascunhos)}},',
	'wikilog-post-count-all'	=>
		'para um total de $1 {{PLURAL:$1|artigo|artigos}}.',
	'wikilog-new-item'			=> 'Criar novo artigo wikilog',
	'wikilog-new-item-go'		=> 'Criar',
	'wikilog-item-name'			=> 'Nome do artigo:',

	# Generic strings
	'wikilog-published'			=> 'Publicado',
	'wikilog-updated'			=> 'Atualizado',
	'wikilog-draft'				=> 'Rascunho',
	'wikilog-authors'			=> 'Autores',
	'wikilog-wikilog'			=> 'Wikilog',
	'wikilog-title'				=> 'Título',
	'wikilog-actions'			=> 'Ações',
	'wikilog-comments'			=> 'Comentários',
	'wikilog-view-archives'		=> 'Arquivos',
	'wikilog-view-summary'		=> 'Resumo',
	'wikilog-draft-title-mark'	=> '(rascunho)',
	'wikilog-anonymous-mark'	=> '(anônimo)',

	# Pager strings
	'wikilog-pager-newer-n'		=> '← $1 próximos',
	'wikilog-pager-older-n'		=> '$1 anteriores →',
	'wikilog-pager-newest'		=> '←← mais recentes',
	'wikilog-pager-oldest'		=> 'mais antigos →→',
	'wikilog-pager-prev'		=> '← anterior',
	'wikilog-pager-next'		=> 'próxima →',
	'wikilog-pager-first'		=> '←← primeira',
	'wikilog-pager-last'		=> 'última →→',
	'wikilog-pager-empty'		=>
		'<div class="wl-empty">(não há itens)</div>',

	# Comments page link text
	'wikilog-no-comments'		=> 'não há comentários',
	'wikilog-has-comments'		=> '{{PLURAL:$1|um comentário|$1 comentários}}',

	'wikilog-author-signature'	=> '[[{{ns:User}}:$1|$1]] ([[{{ns:User_talk}}:$1|discussão]])',
	'wikilog-item-brief-header'	=> ': <i><small>por $5, em [[$1|$2]], $6.</small></i>',
	'wikilog-item-brief-footer'	=> '',
	'wikilog-item-more'			=> '[[$3|&rarr; continuar lendo...]]',
	'wikilog-item-sub'			=> '',
	'wikilog-item-header'		=> '',
	'wikilog-item-footer'		=> ': <i>&mdash; $5 &#8226; $6 &#8226; $7</i>',

	# Comments
	'wikilog-comment-by'		=> 'Comentário por $1 ($2)',
	'wikilog-comment-by-anon'	=> 'Comentário por $3 (anônimo)',
	'wikilog-reply-to-comment'	=> 'Postar uma resposta a esse comentário',
	'wikilog-newtalk-text'		=> '<!-- página em branco criada pelo Wikilog -->',
	'wikilog-newtalk-summary'	=> 'criado automaticamente pelo wikilog',

	# Atom and RSS feeds
	'wikilog-feed-title'		=> '{{SITENAME}} - $1 [$2]', # $1 = title, $2 = content language
	'wikilog-feed-description'	=> 'Leia as postagens mais recentes neste feed.',

	# Warning and error messages
	'wikilog-error-msg'			=> 'Wikilog: $1',
	'wikilog-invalid-param'		=> 'Parâmetro inválido: $1.',
	'wikilog-invalid-author'	=> 'Autor inválido: $1.',
	'wikilog-invalid-date'		=> 'Data inválida: $1.',
	'wikilog-invalid-tag'		=> 'Rótulo inválido: $1.',
	'wikilog-invalid-file'		=> 'Arquivo inválido: $1.',
	'wikilog-file-not-found'	=> 'Arquivo não-existente: $1.',
	'wikilog-not-an-image'		=> 'Arquivo não é uma imagem: $1.',
	'wikilog-out-of-context'	=>
			'Aviso: Rótulos wikilog estão sendo utilizados fora de contexto. ' .
			'Eles devem ser usados apenas em artigos no espaço de nomes do Wikilog.',
	'wikilog-too-many-authors'	=>
			'Aviso: Autores demais listados nesta postagem wikilog.',
	'wikilog-too-many-tags'	=>
			'Aviso: Rótulos demais listados nesta postagem wikilog.',
	'wikilog-comment-is-empty'	=>
			'O comentário em branco.',
	'wikilog-comment-too-long'	=>
			'O comentário postado é muito longo.',
	'wikilog-comment-invalid-name' =>
			'O nome fornecidos é inválido.',

	'wikilog-reading-draft'		=>
			'<div class="mw-warning">'.
			'<p>Este artigo wikilog é um rascunho, ainda não foi publicado.</p>'.
			'</div>',

	'wikilog-posting-anonymously' =>	# $1 = "login" link
			'Você não está autenticado neste momento; seu comentário será '.
			'postado anonimamente, identificado pelo endereço de sua conexão '.
			'Internet. Você deve fornecer um pseudônimo acima para '.
			'identificar seu comentário ou $1 para que ele seja creditado '.
			'apropriadamente.',

	'wikilog-posting-anonymously' =>
			"Seu comentário será postado anonimamente. Você pode " .
			"$1 para que seu comentário seja identificado.",

	# Forms
	'wikilog-post-comment'		=> 'Postar um novo comentário',
	'wikilog-post-reply'		=> 'Postar uma nova resposta',
	'wikilog-form-legend'		=> 'Procurar por postagens wikilog',
	'wikilog-form-wikilog'		=> 'Wikilog:',
	'wikilog-form-category'		=> 'Categoria:',
	'wikilog-form-name'			=> 'Nome:',
	'wikilog-form-author'		=> 'Autor:',
	'wikilog-form-tag'			=> 'Rótulo:',
	'wikilog-form-date'			=> 'Data:',
	'wikilog-form-status'		=> 'Estado:',
	'wikilog-form-preview'		=> 'Previsão:',
	'wikilog-form-comment'		=> 'Comentário:',
	'wikilog-show-all'			=> 'Todas as postagens',
	'wikilog-show-published'	=> 'Publicados',
	'wikilog-show-drafts'		=> 'Rascunhos',
	'wikilog-submit'			=> 'Submeter',
	'wikilog-preview'			=> 'Previsão',		# verb
	'wikilog-reply-lc'			=> 'responder',		# verb
);
