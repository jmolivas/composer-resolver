<?php

declare(strict_types=1);

namespace Toflar\ComposerResolver\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PostActionEvent extends Event
{
    const EVENT_NAME = 'resolver.post_action';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @return Request
     */
    public function getRequest() : Request
    {
        return $this->request;
    }

    /**
     * @param Request $request
     *
     * @return PostActionEvent
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @return Response
     */
    public function getResponse() : ?Response
    {
        return $this->response;
    }

    /**
     * @param Response $response
     *
     * @return PostActionEvent
     */
    public function setResponse(Response $response = null) : self
    {
        $this->response = $response;

        return $this;
    }
}
