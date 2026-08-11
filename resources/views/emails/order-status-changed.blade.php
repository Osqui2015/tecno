<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pedido #{{ $order->id }} — {{ $newLabel }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #334155;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8fafc; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); padding: 32px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 800;">{{ config('app.name') }}</h1>
                        </td>
                    </tr>

                    <!-- Status badge -->
                    <tr>
                        <td style="padding: 32px 32px 0; text-align: center;">
                            @php
                                $badgeColor = match($newStatus) {
                                    'confirmed', 'preparing', 'shipped' => '#3b82f6',
                                    'delivered' => '#10b981',
                                    'cancelled' => '#ef4444',
                                    default => '#f59e0b',
                                };
                            @endphp
                            <span style="display: inline-block; padding: 8px 16px; background-color: {{ $badgeColor }}; color: #ffffff; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border-radius: 999px;">
                                {{ $newLabel }}
                            </span>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 24px 32px;">
                            <h2 style="margin: 0 0 16px; color: #0f172a; font-size: 20px; font-weight: 700;">
                                Tu pedido #{{ $order->id }} ahora está {{ strtolower($newLabel) }}
                            </h2>
                            <p style="margin: 0 0 24px; color: #64748b; font-size: 14px; line-height: 1.6;">
                                Hola <strong>{{ $order->customer_full_name ?: $order->customer_name }}</strong>, te avisamos que el estado de tu pedido cambió.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #f8fafc; border-radius: 12px; padding: 16px; margin-bottom: 24px;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 4px; color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase;">Pedido</p>
                                        <p style="margin: 0 0 12px; color: #0f172a; font-size: 16px; font-weight: 700;">#{{ $order->id }}</p>

                                        <p style="margin: 0 0 4px; color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase;">Estado anterior</p>
                                        <p style="margin: 0 0 12px; color: #64748b; font-size: 14px;">{{ $oldLabel }}</p>

                                        <p style="margin: 0 0 4px; color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase;">Total</p>
                                        <p style="margin: 0 0 12px; color: #0f172a; font-size: 18px; font-weight: 800;">${{ number_format((float) $order->total, 0, ',', '.') }}</p>

                                        <p style="margin: 0 0 4px; color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase;">Items</p>
                                        <p style="margin: 0; color: #0f172a; font-size: 14px;">{{ $order->items->count() }} producto(s)</p>
                                    </td>
                                </tr>
                            </table>

                            @if($newStatus === 'shipped')
                                <p style="margin: 0 0 24px; color: #64748b; font-size: 14px; line-height: 1.6;">
                                    📦 Tu pedido está en camino. Si tenés alguna pregunta sobre el envío, contactanos.
                                </p>
                            @endif

                            @if($newStatus === 'cancelled')
                                <p style="margin: 0 0 24px; color: #64748b; font-size: 14px; line-height: 1.6;">
                                    ❌ Tu pedido fue cancelado. Si tenés alguna duda o querés hacer un nuevo pedido, contactanos.
                                </p>
                            @endif
                        </td>
                    </tr>

                    <!-- CTA -->
                    <tr>
                        <td style="padding: 0 32px 32px; text-align: center;">
                            <a href="{{ url('/mis-pedidos') }}" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); color: #ffffff; font-size: 14px; font-weight: 700; text-decoration: none; border-radius: 8px;">
                                Ver mis pedidos
                            </a>
                        </td>
                    </tr>

                    @if($order->admin_notes)
                        <tr>
                            <td style="padding: 0 32px 32px;">
                                <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 8px;">
                                    <p style="margin: 0 0 4px; color: #92400e; font-size: 11px; font-weight: 700; text-transform: uppercase;">📝 Nota del administrador</p>
                                    <p style="margin: 0; color: #78350f; font-size: 14px; line-height: 1.5;">{{ $order->admin_notes }}</p>
                                </div>
                            </td>
                        </tr>
                    @endif

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 24px 32px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 4px; color: #94a3b8; font-size: 12px;">
                                © {{ date('Y') }} {{ config('app.name') }} · Todos los derechos reservados
                            </p>
                            <p style="margin: 0; color: #cbd5e1; font-size: 11px;">
                                Este email fue enviado a {{ $order->user->email ?? 'tu cuenta' }} porque realizaste un pedido.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
