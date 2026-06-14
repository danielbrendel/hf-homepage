<?php

/*
    Asatru PHP - routes configuration file

    Add here all your needed routes.

    Schema:
        [<url>, <method>, controller_file@controller_method]
    Example:
        [/my/route, get, mycontroller@index]
        [/my/route/with/{param1}/and/{param2}, get, mycontroller@another]
    Explanation:
        Will call index() in app\controller\mycontroller.php if request is 'get'
        Every route with $ prefix is a special route
*/

return [
    array('/', 'GET', 'index@index'),
    array('/screenshots', 'GET', 'index@screenshots'),
    array('/themes', 'GET', 'index@themes'),
    array('/videos', 'GET', 'index@videos'),
    array('/faq', 'GET', 'index@faq'),
    array('/demo', 'GET', 'index@demo'),
    array('/documentation', 'GET', 'index@documentation'),
    array('/community', 'GET', 'community@index'),
    array('/community/fetch', 'POST', 'community@fetch'),
    array('/community/fetch/random', 'ANY', 'community@random'),
    array('/community/fetch/latest', 'ANY', 'community@latest'),
    array('/support', 'GET', 'index@support'),
    array('/support', 'POST', 'index@support_request'),
    array('/partnership', 'GET', 'index@partnership'),
    array('/software/version', 'GET', 'version@get_version'),
    array('/api/photo/share', 'POST', 'api@share_photo'),
    array('/api/photo/remove', 'ANY', 'api@remove_photo'),
    array('/p/{slug}', 'GET', 'api@get_photo'),
    array('/newsletter/subscribe', 'POST', 'newsletter@subscribe'),
    array('/newsletter/confirm', 'GET', 'newsletter@confirm'),
    array('/newsletter/unsubscribe/{token}', 'GET', 'newsletter@unsubscribe'),
    array('/sitemap', 'GET', 'index@sitemap'),
    array('/admin', 'GET', 'admin@index'),
    array('/admin/softver/save', 'POST', 'admin@save_software_version'),
    array('/admin/newsletter/toggle', 'ANY', 'admin@toggle_newsletter'),
    array('/admin/newsletter/go', 'POST', 'admin@send_newsletter'),
    array('/admin/newsletter/process', 'ANY', 'admin@process_newsletter'),
    array('/admin/newsletter/preview', 'POST', 'admin@preview_newsletter'),
    array('/admin/newsletter/clean', 'ANY', 'admin@cleanup_unconfirmed'),
    array('/admin/social/submit', 'POST', 'admin@social_submit'),
    array('/admin/social/post/{platform}', 'ANY', 'admin@social_post'),
    array('$404', 'ANY', 'error404@index')
];
