<?php

/**
 * This class extends the base model class and represents your associated table
 */ 
class SocialModel extends \Asatru\Database\Model {
    const FILE_IDENT = 'asset';
    const SOCIALS = ['mastodon', 'bluesky'];

    /**
     * @param $content
     * @return void
     * @throws \Exception
     */
    public static function addPost($content)
    {
        try {
            static::raw('INSERT INTO `@THIS` (content, asset) VALUES(?, NULL)', [$content]);

            $item = static::raw('SELECT * FROM `@THIS` ORDER BY id DESC LIMIT 1')->first();

            if ((isset($_FILES[self::FILE_IDENT])) && ($_FILES[self::FILE_IDENT]['error'] === UPLOAD_ERR_OK)) {
                $file_ext = ImageModule::getImageExt($_FILES[self::FILE_IDENT]['tmp_name']);

                if ($file_ext === null) {
                    throw new \Exception('File is not a valid image');
                }

                $file_name = md5(random_bytes(55) . date('Y-m-d H:i:s'));

                move_uploaded_file($_FILES[self::FILE_IDENT]['tmp_name'], public_path('/img/social/' . $file_name . '.' . $file_ext));

                static::raw('UPDATE `@THIS` SET asset = ? WHERE id = ?', [$file_name . '.' . $file_ext, $item->get('id')]);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @param $platform
     * @return void
     * @throws \Exception
     */
    public static function publishPost($platform)
    {
        try {
            if (!in_array($platform, self::SOCIALS)) {
                throw new \Exception('Unsupported social network: ' . print_r($platform, true));
            }

            $item = static::raw('SELECT * FROM `@THIS` WHERE ' . $platform . ' = 0 ORDER BY id ASC LIMIT 1')->first();
            if (!$item) {
                return;
            }

            static::$platform($item->get('content'), (($item->get('asset')) ? public_path() . '/img/social/' . $item->get('asset') : null));

            static::raw('UPDATE `@THIS` SET ' . $platform . ' = 1 WHERE id = ?', [$item->get('id')]);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @param $content
     * @param $asset
     * @return void
     * @throws \Exception
     */
    public static function mastodon($content, $asset = null)
    {
        try {
            $server_instance = env('MASTODON_SERVER_INSTANCE');
            $access_token = env('MASTODON_ACCESS_TOKEN');

            $media_id = null;

            if (($asset) && (is_file($asset))) {
                $response = NetUtilsModule::remoteRequest($server_instance . '/api/v2/media', [
                    'header' => [
                        'Authorization: Bearer ' . $access_token,
                        'Content-Type: multipart/form-data'
                    ],
                    'post' => [
                        'file' => new \CURLFile($asset)
                    ]
                ]);
                
                $media_json = json_decode($response['data']);
                if (isset($media_json->error)) {
                    throw new \Exception('[api/v2/media] ' . $media_json->error, $response['info']['http_code']);
                }

                $media_id = $media_json->id;
            }

            $post_data = [
                'status' => $content,
                'visibility' => 'public'
            ];

            if ($media_id) {
                $post_data['media_ids'] = [$media_id];
            }

            $response = NetUtilsModule::remoteRequest($server_instance . '/api/v1/statuses', [
                'header' => [
                    'Authorization: Bearer ' . $access_token,
                    'Content-Type: application/json'
                ],
                'post' => json_encode($post_data)
            ]);

            $status_json = json_decode($response['data']);
            if (isset($status_json->error)) {
                throw new \Exception('[api/v1/statuses] ' . $status_json->error, $response['info']['http_code']);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @param $content
     * @param $asset
     * @return void
     * @throws \Exception
     */
    public static function bluesky($content, $asset = null)
    {
        try {
            $instance = env('BLUESKY_INSTANCE');
            $handle = env('BLUESKY_HANDLE');
            $password = env('BLUESKY_PASSWORD');

            $tags = null;
            $tagfacets = [];
            $facets = [];
            $embed = null;

            $response = NetUtilsModule::remoteRequest($instance . '/xrpc/com.atproto.server.createSession', [
                'header' => [
                    'Content-Type: application/json'
                ],
                'post' => json_encode([
                    'identifier' => $handle,
                    'password' => $password
                ])
            ]);
            
            $session = json_decode($response['data']);
            
            if ((!isset($session->accessJwt)) || (!isset($session->did))) {
                throw new \Exception('accessJwt or did are missing: ' . print_r($response, true));
            }

            preg_match_all('/#(\w+)/', $content, $tags);

            if (count($tags[1]) > 0) {
                foreach ($tags[1] as $tag) {
                    $tagstart = strpos($content, '#' . $tag);
    
                    $tagfacets[] = [
                        'index' => [
                            'byteStart' => (int)$tagstart,
                            'byteEnd' => $tagstart + strlen($tag) + 1
                        ],
                        'features' => [
                            [
                                '$type' => 'app.bsky.richtext.facet#tag',
                                'tag' => $tag
                            ]
                        ]
                    ];
                }

                $facets = array_merge($facets, $tagfacets);
            }

            if (($asset) && (is_file($asset))) {
                $response = NetUtilsModule::remoteRequest($instance . '/xrpc/com.atproto.repo.uploadBlob', [
                    'header' => [
                        'Authorization: Bearer ' . $session->accessJwt,
                        'Content-Type: ' . mime_content_type($asset)
                    ],
                    'post' => file_get_contents($asset)
                ]);
                
                if (!isset($response['data'])) {
                    throw new \Exception('Failed to upload file: ' . print_r($response, true));
                }

                $blob = json_decode($response['data'], true);
                list($width, $height) = getimagesize($asset);

                $embed = [
                    '$type'  => 'app.bsky.embed.images',
                    'images' => [
                        [
                            'alt'   => 'N/A',
                            'image' => $blob['blob'],
                            'aspectRatio' => [
                                'width' => $width,
                                'height' => $height
                            ]
                        ]
                    ]
                ];
            }

            $record = [
                '$type' => 'app.bsky.feed.post',
                'text' => $content,
                'createdAt' => gmdate("Y-m-d\TH:i:s\Z"),
            ];

            if ((is_array($facets)) && (count($facets) > 0)) {
                $record['facets'] = $facets;
            }

            if (($embed) && (is_array($embed))) {
                $record['embed'] = $embed;
            }
            
            $response = NetUtilsModule::remoteRequest($instance . '/xrpc/com.atproto.repo.createRecord', [
                'header' => [
                    'Authorization: Bearer ' . $session->accessJwt,
                    'Content-Type: application/json'
                ],
                'post' => json_encode([
                    'repo' => $session->did,
                    'collection' => 'app.bsky.feed.post',
                    'record' => $record
                ])
            ]);

            if (!isset($response['data'])) {
                throw new \Exception('Request failed: ' . print_r($response, true));
            }

            $status_json = json_decode($response['data']);
            if (isset($status_json->error)) {
                throw new \Exception('Erroneous request: ' . $status_json->error, $response['info']['http_code']);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}