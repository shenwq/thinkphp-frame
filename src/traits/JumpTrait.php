<?php

namespace ffhome\frame\traits;

use think\exception\HttpResponseException;
use think\Response;

trait JumpTrait
{
    /**
     * 分页查询成功返回
     * @param int $count
     * @param mixed $list
     * @return \think\response\Json
     */
    protected function successPage(int $count, $list)
    {
        $data = [
            'code' => config('code.success'),
            'msg' => '',
            'count' => $count,
            'data' => $list,
        ];
        return json($data);
    }

    /**
     * 操作成功跳转的快捷方法
     * @access protected
     * @param mixed $msg 提示信息
     * @param mixed $data 返回的数据
     * @param string $url 跳转的 URL 地址
     * @param int $wait 跳转等待时间
     * @return void
     * @throws HttpResponseException
     */
    protected function success($msg = '', $data = '', string $url = null, int $wait = 1)
    {
        if (is_null($url) && isset($_SERVER["HTTP_REFERER"])) {
            $url = $_SERVER["HTTP_REFERER"];
        } elseif ($url) {
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : app('route')->buildUrl($url)->__toString();
        }

        $result = [
            'code' => config('code.success'),
            'msg' => $msg,
            'data' => $data,
            'url' => $url,
            'wait' => $wait,
        ];

        $type = $type = $this->getResponseType();
        if ($type == 'html') {
            $response = view(app('config')->get('app.dispatch_success_tmpl'), $result);
        } elseif ($type == 'json') {
            $response = json($result);
        }
        throw new HttpResponseException($response);
    }

    /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param mixed $msg 提示信息
     * @param mixed $data 返回的数据
     * @param string $url 跳转的 URL 地址
     * @param int $wait 跳转等待时间
     * @return void
     * @throws HttpResponseException
     */
    protected function error($msg = '', $data = '', string $url = null, int $wait = 3)
    {
        if (is_null($url)) {
            $url = request()->isAjax() ? '' : 'javascript:history.back(-1);';
        } elseif ($url) {
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : app('route')->buildUrl($url)->__toString();
        }

        $type = $this->getResponseType();
        $result = [
            'code' => config('code.fail'),
            'msg' => $msg,
            'data' => $data,
            'url' => $url,
            'wait' => $wait,
        ];
        if ($type == 'html') {
            $response = view(app('config')->get('app.dispatch_error_tmpl'), $result);
        } elseif ($type == 'json') {
            $response = json($result);
        }
        throw new HttpResponseException($response);
    }

    /**
     * 返回封装后的 API 数据到客户端
     * @access protected
     * @param mixed $data 要返回的数据
     * @param int $code 返回的 code
     * @param mixed $msg 提示信息
     * @param string $type 返回数据格式
     * @param array $header 发送的 Header 信息
     * @return void
     * @throws HttpResponseException
     */
    protected function result($data, int $code = 0, $msg = '', string $type = '', array $header = [])
    {
        $result = [
            'code' => $code,
            'msg' => $msg,
            'time' => time(),
            'data' => $data,
        ];
        $type = $type ?: $this->getResponseType();
        $response = Response::create($result, $type)->header($header);

        throw new HttpResponseException($response);
    }

    /**
     * 返回封装后的 json 数据到客户端
     * @access protected
     * @param mixed $data 要返回的数据
     * @param mixed $msg 提示信息
     * @param int $code 返回的 code
     * @param array $header 发送的 Header 信息
     * @return void
     * @throws HttpResponseException
     */
    protected function json_result($data, $msg = '', int $code = 0, array $header = [])
    {
        $this->result($data, $code, $msg, 'json', $header);
    }

    /**
     * URL 重定向
     * @access protected
     * @param string $url 跳转的 URL 表达式
     * @param int $code http code
     * @return void
     * @throws HttpResponseException
     */
    protected function redirect(string $url = '', int $code = 302)
    {
        $response = Response::create($url, 'redirect', $code);
        throw new HttpResponseException($response);
    }

    /**
     * 获取当前的 response 输出类型
     * @access protected
     * @return string
     */
    protected function getResponseType(): string
    {
        return (request()->isJson() || request()->isAjax() || request()->isPost()) ? 'json' : 'html';
    }
}