<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Facades\Http;

/**
 * چکِ وضعیتِ یک مقصدِ لینک با ردیابیِ کاملِ زنجیرهٔ ریدایرکت.
 * ابتدا HEAD (سبک)؛ اگر سرور HEAD را پشتیبانی نکرد، GET.
 */
class LinkChecker
{
    /** کدهایی که یعنی «HEAD پشتیبانی نمی‌شود» → با GET دوباره تلاش کن. */
    private const HEAD_UNSUPPORTED = [405, 501, 400, 403];

    /**
     * @return array{status_code:int,final_url:?string,redirect_count:int,redirect_chain:list<string>,is_broken:bool,is_redirect:bool,error:?string}
     */
    public function check(string $url): array
    {
        $result = $this->request($url, 'HEAD');

        // بعضی سرورها/CDNها به HEAD پاسخِ درست نمی‌دهند → با GET دوباره.
        if ($result['status_code'] === 0 || in_array($result['status_code'], self::HEAD_UNSUPPORTED, true)) {
            $get = $this->request($url, 'GET');
            if ($get['status_code'] !== 0) {
                $result = $get;
            }
        }

        return $result;
    }

    /**
     * @return array{status_code:int,final_url:?string,redirect_count:int,redirect_chain:list<string>,is_broken:bool,is_redirect:bool,error:?string}
     */
    private function request(string $url, string $method): array
    {
        try {
            $req = Http::timeout(15)
                ->connectTimeout(10)
                ->withHeaders(['User-Agent' => OnPageAuditor::userAgent()])
                ->withOptions(['allow_redirects' => ['track_redirects' => true, 'max' => 10]]);

            $resp = $method === 'HEAD' ? $req->head($url) : $req->get($url);
        } catch (\Throwable $e) {
            return [
                'status_code' => 0,
                'final_url' => null,
                'redirect_count' => 0,
                'redirect_chain' => [],
                'is_broken' => true,
                'is_redirect' => false,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ];
        }

        $status = $resp->status();
        $history = (string) $resp->header('X-Guzzle-Redirect-History');
        $hops = $history !== '' ? array_values(array_filter(explode(', ', $history))) : [];
        $redirectCount = count($hops);
        $finalUrl = $hops !== [] ? end($hops) : $url;

        return [
            'status_code' => $status,
            'final_url' => $finalUrl,
            'redirect_count' => $redirectCount,
            'redirect_chain' => $hops,
            'is_broken' => $status === 0 || $status >= 400,
            'is_redirect' => $redirectCount > 0 || ($finalUrl !== $url && $status >= 200 && $status < 300),
            'error' => null,
        ];
    }
}
