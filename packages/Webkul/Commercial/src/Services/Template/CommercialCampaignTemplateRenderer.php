<?php

namespace Webkul\Commercial\Services\Template;

class CommercialCampaignTemplateRenderer
{
    /**
     * All supported placeholder names with their descriptions.
     * Used to generate the UI reference panel.
     */
    public const VARIABLES = [
        'name' => 'Nome completo do destinatário',
        'first_name' => 'Primeiro nome do destinatário',
        'organization' => 'Empresa/organização do destinatário',
        'email' => 'E-mail do destinatário',
        'phone' => 'Telefone do destinatário',
        'entity_type' => 'Tipo: person ou organization',
        'products' => 'Produtos associados (separados por vírgula)',
        'commercial_statuses' => 'Status comerciais (separados por vírgula)',
        'channel' => 'Canal de envio: email ou whatsapp',
        'campaign.name' => 'Nome da campanha',
        'campaign.channel' => 'Canal da campanha',
    ];

    /**
     * Render a template string for a given context.
     *
     * Placeholders use the syntax {{variable}} or {{ variable }}.
     * Unknown placeholders are silently replaced with an empty string.
     */
    public function render(string $template, TemplateRenderContext $context): string
    {
        $vars = $context->toVars();

        return preg_replace_callback(
            '/\{\{\s*([\w.]+)\s*\}\}/',
            static fn (array $matches): string => $vars[$matches[1]] ?? '',
            $template
        );
    }

    /**
     * Render a message body template.
     */
    public function renderBody(string $template, TemplateRenderContext $context): string
    {
        return $this->render($template, $context);
    }

    /**
     * Render a subject line template.
     */
    public function renderSubject(string $template, TemplateRenderContext $context): string
    {
        return $this->render($template, $context);
    }
}
