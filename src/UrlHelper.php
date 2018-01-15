<?php

namespace OSS\WP;

class UrlHelper
{

    public function __construct()
    {
        add_filter('upload_dir', array($this, 'resetUploadBaseUrl'), 30 );

        if (Config::$enableImgService) {
            add_filter('wp_get_attachment_metadata', array($this, 'replaceImgMeta'), 900);

            if (Config::$enableImgStyle && Config::$sourceImgProtect) {
                add_filter('wp_get_attachment_url', array($this,'replaceImgUrl'), 30, 2);
                add_filter('wp_calculate_image_srcset', array($this, 'replaceImgSrcset'), 900);
            }
        }
    }

    /**
     * 修改从数据库中取出的图片信息，以使用 Aliyun 的图片服务
     * 仅在开启图片服务时启用
     *
     * @param $data
     * @return mixed
     */
    public function replaceImgMeta($data)
    {
        if (empty($data['sizes']))
            return $data;

        $basename = pathinfo($data['file'], PATHINFO_BASENAME);
        $styles = get_intermediate_image_sizes();
        $styles[] = 'full';

        if (Config::$enableImgStyle) {
            foreach ($styles as $style) {
                if (isset($data['sizes'][$style]))
                    $data['sizes'][$style]['file'] = $this->aliImageStyle($basename, $style);
            }
        } else {
            foreach ($data['sizes'] as $size => $info)
                $data['sizes'][$size]['file'] = $this->aliImageResize($basename, $info['height'], $info['width']);
        }

        return $data;
    }

    /**
     * 重置图片链接, 仅在开启图片服务时启用
     *
     * @param $url
     * @param $post_id
     * @return mixed
     */
    public function replaceImgUrl($url, $post_id)
    {
        if (wp_attachment_is_image($post_id))
            $url = $this->aliImageStyle($url, 'full');
        return $url;
    }

    /**
     * 重置 Srcset 中原图链接, 仅在开启图片服务时启用
     *
     * @param $sources
     * @return mixed
     */
    public function replaceImgSrcset($sources)
    {
        foreach ($sources as $k => $source) {
            if (false === strstr($source['url'], Config::$customSeparator))
                $sources[$k]['url'] = $this->aliImageStyle($source['url'], 'full');
        }
        return $sources;
    }

    /**
     * 设置 upload_url_path，使用外部存储OSS
     *
     * @param $uploads
     * @return mixed
     */
    public function resetUploadBaseUrl($uploads)
    {
        if (Config::$staticHost) {
            $base_url = rtrim(Config::$staticHost . Config::$storePath, '/');
            $uploads['baseurl'] = $base_url;
        }
        return $uploads;
    }

    protected function aliImageResize($file, $height, $width)
    {
        return "{$file}?x-oss-process=image/resize,m_fill,h_{$height},w_{$width}";
    }

    protected function aliImageStyle($file, $style)
    {
        if (pathinfo($file, PATHINFO_EXTENSION) == 'gif')
            return $file;
        elseif ($style == 'full' && !Config::$sourceImgProtect)
            return $file;
        else
            return $file . Config::$customSeparator . $style;
    }
}
