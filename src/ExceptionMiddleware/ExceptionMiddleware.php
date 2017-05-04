<?php

/**
 * Copyright (c) 2010-2017 Romain Cottard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eureka\Middleware\ExceptionMiddleware;

use Eureka\Component\Config\Config;
use Eureka\Component\Container\Container;
use Eureka\Component\Http\Message\Response;
use Eureka\Component\Psr\Http\Middleware\DelegateInterface;
use Eureka\Component\Psr\Http\Middleware\ServerMiddlewareInterface;
use Eureka\Middleware\Routing\Exception\RouteNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ExceptionMiddleware implements ServerMiddlewareInterface
{
    /**
     * @var Config config
     */
    protected $config = null;

    /**
     * ExceptionMiddleware constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;

        $this->initDisplay($config);
    }

    /**
     * @param ServerRequestInterface  $request
     * @param DelegateInterface $frame
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $frame)
    {
        try {
            $response = $frame->next($request);
        } catch (\Exception $exception) {
            $response = $this->getErrorResponse($request, $exception);
        }

        return $response;
    }

    /**
     * Init display errors
     *
     * @param  Config $config
     * @return void
     */
    private function initDisplay(Config $config)
    {
        Container::getInstance()->get('error')->init(
            (int) $config->get('global.error.reporting'),
            $config->get('global.error.display')
        );
    }

    /**
     * Get Error response.
     *
     * @param  ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function getErrorResponse(ServerRequestInterface $request, \Exception $exception)
    {
        $httpCode  = ($exception instanceof RouteNotFoundException ? 404 : 500);
        $isAjax    = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        $isDisplay = $this->config->get('global.error.display');

        $response = new Response($httpCode);

        $exceptionDetail = ($isDisplay ? $exception->getTraceAsString() : '');

        if ($isAjax) {

            //~ Ajax response error
            $content = new \stdClass();
            $content->message = $exception->getMessage();
            $content->code    = $exception->getCode();
            $content->trace   = $exceptionDetail;

            $content = json_encode($content);

        } elseif (null !== $request->getAttribute('twigLoader', null)) {

            //~ Twig response error
            $twigLoader      = $request->getAttribute('twigLoader');
            $twig            = new \Twig_Environment($twigLoader);
            $exceptionDetail = PHP_EOL . $exception->getMessage() . PHP_EOL . $exceptionDetail;

            $content = $twig->render('@template/Common/Content/' . $httpCode . '.twig', ['exceptionDetail' => $exceptionDetail]);

        } else {

            //~ Basic html response error
            $content = '<pre>exception: ' . PHP_EOL . $exception->getMessage() . PHP_EOL . $exceptionDetail. '</pre>';
        }

        //~ Write content
        $response->getBody()->write($content);

        return $response;
    }
}