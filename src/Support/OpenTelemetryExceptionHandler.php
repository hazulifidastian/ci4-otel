<?php

/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Hazuli\Ci4Otel\Support;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use Config\Paths;
use Hazuli\Ci4Otel\Filters\KindServerTrace;
use Hazuli\Ci4Otel\Filters\RequestTotalMetric;
use Throwable;

final class OpenTelemetryExceptionHandler extends BaseExceptionHandler implements ExceptionHandlerInterface
{
    use ResponseTrait;

    /**
     * ResponseTrait needs this.
     */
    private ?RequestInterface $request = null;

    /**
     * ResponseTrait needs this.
     */
    private ?ResponseInterface $response = null;

    /**
     * Determines the correct way to display the error.
     */
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode
    ): void {
        // ResponseTrait needs these properties.
        $this->request  = $request;
        $this->response = $response;

        if ($request instanceof IncomingRequest) {
            try {
                $response->setStatusCode($statusCode);
            } catch (HTTPException $e) {
                // Workaround for invalid HTTP status code.
                $statusCode = 500;
                $response->setStatusCode($statusCode);
            }

            if (! headers_sent()) {
                header(
                    sprintf(
                        'HTTP/%s %s %s',
                        $request->getProtocolVersion(),
                        $response->getStatusCode(),
                        $response->getReasonPhrase()
                    ),
                    true,
                    $statusCode
                );
            }

            $this->openTelemetryCaptureData($request, $response);

            if (strpos($request->getHeaderLine('accept'), 'text/html') === false) {
                $data = (ENVIRONMENT === 'development' || ENVIRONMENT === 'testing')
                    ? $this->collectVars($exception, $statusCode)
                    : '';

                $this->respond($data, $statusCode)->send();

                if (ENVIRONMENT !== 'testing') {
                    // @codeCoverageIgnoreStart
                    exit($exitCode);
                    // @codeCoverageIgnoreEnd
                }

                return;
            }
        }

        // Determine possible directories of error views
        $addPath = ($request instanceof IncomingRequest ? 'html' : 'cli') . DIRECTORY_SEPARATOR;
        $path    = $this->viewPath . $addPath;
        $altPath = rtrim((new Paths())->viewDirectory, '\\/ ')
            . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . $addPath;

        // Determine the views
        $view    = $this->determineView($exception, $path);
        $altView = $this->determineView($exception, $altPath);

        // Check if the view exists
        $viewFile = null;
        if (is_file($path . $view)) {
            $viewFile = $path . $view;
        } elseif (is_file($altPath . $altView)) {
            $viewFile = $altPath . $altView;
        }

        // Displays the HTML or CLI error code.
        $this->render($exception, $statusCode, $viewFile);

        if (ENVIRONMENT !== 'testing') {
            // @codeCoverageIgnoreStart
            exit($exitCode);
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Determines the view to display based on the exception thrown,
     * whether an HTTP or CLI request, etc.
     *
     * @return string The filename of the view file to use
     */
    protected function determineView(Throwable $exception, string $templatePath): string
    {
        // Production environments should have a custom exception file.
        $view = 'production.php';

        if (str_ireplace(['off', 'none', 'no', 'false', 'null'], '', ini_get('display_errors'))) {
            $view = 'error_exception.php';
        }

        // 404 Errors
        if ($exception instanceof PageNotFoundException) {
            return 'error_404.php';
        }

        $templatePath = rtrim($templatePath, '\\/ ') . DIRECTORY_SEPARATOR;

        // Allow for custom views based upon the status code
        if (is_file($templatePath . 'error_' . $exception->getCode() . '.php')) {
            return 'error_' . $exception->getCode() . '.php';
        }

        return $view;
    }

    private function openTelemetryCaptureData(RequestInterface $request, ResponseInterface $response): void
    {
        $kindServerTrace = new KindServerTrace();
        $kindServerTrace->after($request, $response);

        $requestTotalMetric = new RequestTotalMetric();
        $requestTotalMetric->after($request, $response);

        $requestLatencyMetric = new RequestTotalMetric();
        $requestLatencyMetric->after($request, $response);
    }
}
