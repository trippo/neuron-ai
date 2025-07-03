<?php

declare(strict_types=1);

namespace NeuronAI;

use GuzzleHttp\Promise\PromiseInterface;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Observability\Events\AgentError;
use NeuronAI\Observability\Events\MessageSaved;
use NeuronAI\Observability\Events\MessageSaving;
use NeuronAI\Observability\Events\InferenceStart;
use NeuronAI\Observability\Events\InferenceStop;

trait HandleChat
{
    /**
     * Execute the chat.
     *
     * @throws \Throwable
     */
    public function chat(Message|array $messages): Message
    {
        return $this->chatAsync($messages)->wait();
    }

    public function chatAsync(Message|array $messages): PromiseInterface
    {
        $this->notify('chat-start');

        $this->fillChatHistory($messages);

        $tools = $this->bootstrapTools();

        $this->notify(
            'inference-start',
            new InferenceStart($this->resolveChatHistory()->getLastMessage())
        );

        return $this->resolveProvider()
            ->systemPrompt($this->resolveInstructions())
            ->setTools($tools)
            ->chatAsync(
                $this->resolveChatHistory()->getMessages()
            )->then(function (Message $response): Message|PromiseInterface {
                $this->notify(
                    'inference-stop',
                    new InferenceStop($this->resolveChatHistory()->getLastMessage(), $response)
                );

                if ($response instanceof ToolCallMessage) {
                    $toolCallResult = $this->executeTools($response);
                    return $this->chatAsync([$response, $toolCallResult]);
                }
                $this->notify('message-saving', new MessageSaving($response));
                $this->resolveChatHistory()->addMessage($response);
                $this->notify('message-saved', new MessageSaved($response));

                $this->notify('chat-stop');
                return $response;
            }, function (\Throwable $exception): void {
                $this->notify('error', new AgentError($exception));
                throw $exception;
            });
    }
}
