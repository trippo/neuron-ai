<?php

namespace NeuronAI\MCP;

class McpClient
{
    private McpTransportInterface $transport;

    private int $requestId = 0;

    /**
     * Create a new MCP client with the given transport
     */
    public function __construct(array $config)
    {
        if (\array_key_exists('command', $config)) {
            $this->transport = new StdioTransport($config);
            $this->transport->connect();
        } else {
            // todo: implement support for SSE with URL config property
            throw new \Exception('Transport not supported!');
        }
    }

    /**
     * List all available tools from the MCP server
     *
     * @throws \Exception
     */
    public function listTools($cursor = null): array
    {
        $tools = [];

        do {
            $request = [
                "jsonrpc" => "2.0",
                "id" => ++$this->requestId,
                "method" => "tools/list",
            ];

            // Eventually add pagination
            if (isset($response['result']['nextCursor'])) {
                $request['params'] = ['cursor' => $response['result']['nextCursor']];
            }

            $this->transport->send($request);
            $response = $this->transport->receive();

            $tools = \array_merge($tools, $response['result']['tools']);
        } while (isset($response['result']['nextCursor']));

        return $tools;
    }

    /**
     * Call a tool on the MCP server
     *
     * @throws \Exception
     */
    public function callTool($toolName, $arguments = []): array
    {
        $request = [
            "jsonrpc" => "2.0",
            "id" => ++$this->requestId,
            "method" => "tools/call",
            "params" => [
                "name" => $toolName,
                "arguments" => $arguments
            ]
        ];

        $this->transport->send($request);
        return $this->transport->receive();
    }
}
