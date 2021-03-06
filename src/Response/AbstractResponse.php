<?php

namespace ChromaX\BasicHttpClient\Response;

use ChromaX\BasicHttpClient\Request\RequestInterface;
use ChromaX\BasicHttpClient\Response\Header\Header;
use ChromaX\BasicHttpClient\Response\Statistics\Statistics;
use ChromaX\BasicHttpClient\Util\HeaderNameUtil;

/**
 * Class AbstractResponse
 *
 * @package ChromaX\BasicHttpClient\Response
 */
abstract class AbstractResponse implements ResponseInterface
{

	/**
	 * @var RequestInterface
	 */
	private $request;

	/**
	 * @var int
	 */
	private $statusCode;

	/**
	 * @var string
	 */
	private $statusText;

	/**
	 * @var Header[]
	 */
	private $headers;

	/**
	 * @var mixed
	 */
	private $body;

	/**
	 * @var Statistics
	 */
	private $statistics;

	/**
	 * Response constructor.
	 *
	 * @param RequestInterface $request
	 */
	public function __construct(RequestInterface $request)
	{
		$this->request = $request;
	}

	/**
	 * @param resource $curl
	 * @param string $responseBody
	 * @return $this
	 */
	public function populateFromCurlResult($curl, string $responseBody)
	{
		if (!is_resource($curl)) {
			$argumentType = (is_object($curl)) ? get_class($curl) : gettype($curl);
			throw new \TypeError('curl argument invalid. Expected a valid resource. Got ' . $argumentType);
		}
		$this->statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$this->setStatistics($curl);
		$this->setResponseData($responseBody);
		return $this;
	}

	/**
	 * @return RequestInterface
	 */
	public function getRequest(): RequestInterface
	{
		return $this->request;
	}

	/**
	 * @return int
	 */
	public function getStatusCode(): ?int
	{
		return $this->statusCode;
	}

	/**
	 * @return string
	 */
	public function getStatusText(): ?string
	{
		return $this->statusText;
	}

	/**
	 * @return Header[]
	 */
	public function getHeaders(): ?array
	{
		return $this->headers;
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function hasHeader(string $name): bool
	{
		return !is_null($this->getHeader($name));
	}

	/**
	 * @param string $name
	 * @return Header
	 */
	public function getHeader(string $name): ?Header
	{
		$headers = $this->getHeaders();
		$headerNameUtil = new HeaderNameUtil();
		$name = $headerNameUtil->normalizeHeaderName($name);
		foreach ($headers as $header) {
			if ($header->getNormalizedName() === $name) {
				return $header;
			}
		}
		return null;
	}

	/**
	 * @return mixed
	 */
	public function getBody()
	{
		return $this->body;
	}

	/**
	 * @return Statistics
	 */
	public function getStatistics(): ?Statistics
	{
		return $this->statistics;
	}

	/**
	 * @param resource $curl
	 * @return $this
	 */
	protected function setStatistics($curl)
	{
		$this->statistics = new Statistics();
		$this->statistics->populateFromCurlResult($curl);
		return $this;
	}

	/**
	 * @param string $responseBody
	 * @return $this
	 */
	protected function setResponseData(string $responseBody)
	{
		// Parse response
		$responseHeaders = array();
		$responseStatusText = null;
		$responseStatusCode = null;
		if (strpos($responseBody, "\r\n\r\n") !== false) {
			/** @noinspection SuspiciousBinaryOperationInspection */
			do {
				list($responseHeader, $responseBody) = explode("\r\n\r\n", $responseBody, 2);
				$responseHeaderLines = explode("\r\n", $responseHeader);
				$responseStatusText = $responseHeaderLines[0];
				$responseStatusCode = (int)substr(
					trim($responseStatusText),
					strpos($responseStatusText, ' ') + 1,
					3
				);
			} while (
				strpos($responseBody, "\r\n\r\n") !== false
				&& (
					!($responseStatusCode >= 200 && $responseStatusCode < 300)
					|| !$responseStatusCode >= 400
				)
			);
			$responseHeaders = preg_split('/\r\n/', $responseHeader, null, PREG_SPLIT_NO_EMPTY);
		}
		$this->setResponseHeader($responseHeaders);
		if (!is_null($responseStatusCode)) {
			$this->setStatusCode($responseStatusCode);
		}
		if (!is_null($responseStatusText)) {
			$this->setStatusText($responseStatusText);
		}
		$this->setBody($responseBody);
		return $this;
	}

	/**
	 * @param int $statusCode
	 * @return $this
	 */
	protected function setStatusCode(int $statusCode)
	{
		$this->statusCode = $statusCode;
		return $this;
	}

	/**
	 * @param string $statusText
	 * @return $this
	 */
	protected function setStatusText(string $statusText)
	{
		$this->statusText = $statusText;
		return $this;
	}

	/**
	 * @param mixed $body
	 * @return $this
	 */
	protected function setBody($body)
	{
		$this->body = $body;
		return $this;
	}

	/**
	 * @param string[] $responseHeaders
	 * @return $this
	 */
	protected function setResponseHeader(array $responseHeaders)
	{
		foreach ($responseHeaders as $responseHeader) {
			if (strpos($responseHeader, ':') !== false) {
				$headerName = mb_substr($responseHeader, 0, strpos($responseHeader, ':'));
				$headerValue = mb_substr($responseHeader, strpos($responseHeader, ':') + 1);
				$headerValues = explode(',', $headerValue);
				$this->headers[] = new Header($headerName, $headerValues);
			}
		}
		return $this;
	}

}
