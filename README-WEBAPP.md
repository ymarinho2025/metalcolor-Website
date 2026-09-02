# Metal Color WebApp v3

E-commerce Metal Color com checkout Asaas, split de comissão, frete por CEP, conta do cliente, painel do vendedor e acompanhamento de entrega.

## Fluxo

Produto → carrinho → login opcional → CEP/frete → Checkout Asaas → Webhook → pedido pago → preparação → envio → rastreio → entrega.

## Principais áreas

- `/pagamento/`: checkout, endereço e frete.
- `/cadastro/` e `/login/`: conta do cliente/vendedor.
- `/conta/`: carrinho salvo, compras recentes e comprar novamente.
- `/pedido/`: pagamento, andamento, rastreio e WhatsApp.
- `/admin/`: pedidos, clientes e atualização de envio.

Consulte `SETUP.md` para configurar Asaas, banco, frete, administrador e rastreamento dos Correios. Consulte `SEGURANCA.md` para as proteções aplicadas.
