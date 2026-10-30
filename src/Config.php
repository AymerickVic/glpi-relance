<?php

/**
 * GLPI Assist — configuration (par entité, héritage depuis l'entité racine).
 * Licence GPLv3+, voir LICENSE.
 */

namespace GlpiPlugin\Glpiassist;

use Auth;
use CommonDBTM;
use CommonGLPI;
use GLPIKey;
use Html;
use Profile;
use Profile_User;
use Session;
use User;

class Config extends CommonDBTM
{
    public static $rightname = 'config';

    public const TECH_USER_LOGIN = 'glpiassist';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_glpiassist_configs';
    }

    public static function getTypeName($nb = 0)
    {
        return 'GLPI Assist';
    }

    /**
     * Configuration applicable à une entité : la sienne si elle existe, sinon celle de l'ancêtre le plus proche
     * qui en a une, jusqu'à l'entité racine.
     */
    public static function forEntity(int $entities_id): array
    {
        $chaine = array_merge([$entities_id], array_reverse(array_values(getAncestorsOf('glpi_entities', $entities_id))));
        foreach (array_unique($chaine) as $e) {
            if ($row = self::rowFor((int) $e)) {
                return $row;
            }
        }
        return self::rowFor(0) ?? ['entities_id' => 0, 'is_active' => 0, 'provider' => 'dry', 'model' => '', 'endpoint' => '', 'api_key' => '', 'language' => 'fr', 'excluded_categories' => []];
    }

    /** La ligne propre à une entité, déchiffrée, ou null si elle hérite. */
    public static function rowFor(int $entities_id): ?array
    {
        global $DB;
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['entities_id' => $entities_id]]) as $row) {
            $row['api_key'] = (new GLPIKey())->decrypt((string) $row['api_key']);
            $row['excluded_categories'] = array_filter(array_map('intval', explode(',', (string) $row['excluded_categories'])));
            return $row;
        }
        return null;
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareKey($input);
    }

    public function prepareInputForAdd($input)
    {
        return $this->prepareKey($input);
    }

    /** La clé n'est jamais stockée en clair. Un champ vide signifie « ne pas changer ». */
    private function prepareKey(array $input): array
    {
        if (array_key_exists('api_key', $input)) {
            if ($input['api_key'] === '') {
                unset($input['api_key']);
            } else {
                $input['api_key'] = (new GLPIKey())->encrypt($input['api_key']);
            }
        }
        if (isset($input['excluded_categories']) && is_array($input['excluded_categories'])) {
            $input['excluded_categories'] = implode(',', array_map('intval', $input['excluded_categories']));
        }
        $input['date_mod'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        return $input;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$withtemplate && ($item->getType() === 'Config' || $item->getType() === 'Entity')) {
            return 'GLPI Assist';
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item->getType() === 'Config') {
            (new self())->showForm(0);
        } elseif ($item->getType() === 'Entity') {
            (new self())->showForm(0, ['entities_id' => (int) $item->getID()]);
        }
        return true;
    }

    /**
     * Formulaire d'une entité. L'entité racine a toujours sa ligne ; une autre entité hérite tant qu'elle n'a pas la sienne.
     */
    public function showForm($ID, array $options = [])
    {
        if (!Session::haveRight('config', UPDATE)) {
            return false;
        }

        $entities_id = (int) ($options['entities_id'] ?? 0);
        $propre = self::rowFor($entities_id);
        $row = $propre ?? [];
        $herite = $entities_id > 0 && $propre === null;
        $keyHint = !empty($row['api_key']) ? 'configurée (…' . substr((string) $row['api_key'], -4) . ')' : 'aucune';

        if ($entities_id > 0) {
            $source = self::forEntity($entities_id);
            $nomSource = \Dropdown::getDropdownName('glpi_entities', (int) $source['entities_id']);
            echo "<div class='alert alert-info'>" . ($herite
                ? "Cette entité hérite de la configuration de « $nomSource ». Pour lui donner la sienne, enregistrez ce formulaire."
                : 'Cette entité a sa propre configuration.') . '</div>';
        }

        $providers = [
            'dry'       => 'Mode à blanc (aucun appel, pour tester le circuit)',
            'openai'    => 'OpenAI (clé API)',
            'anthropic' => 'Anthropic (clé API)',
            'ollama'    => 'Ollama (local)',
        ];

        echo "<form method='post' action='" . self::getFormURL() . "'>";
        echo "<input type='hidden' name='id' value='" . (int) ($row['id'] ?? 0) . "'>";
        echo "<input type='hidden' name='entities_id' value='$entities_id'>";
        echo "<div class='card'><div class='card-body'>";
        echo "<h3>GLPI Assist — relance des tickets incomplets</h3>";
        echo "<p class='text-muted'>Le plugin tourne dans ce GLPI. La seule connexion sortante va vers le fournisseur choisi, avec votre clé. Rien ne transite ailleurs.</p>";

        echo "<div class='mb-3'><label class='form-label'>Activer</label>";
        \Dropdown::showYesNo('is_active', (int) ($row['is_active'] ?? 0));
        echo '</div>';

        echo "<div class='mb-3'><label class='form-label'>Fournisseur</label>";
        \Dropdown::showFromArray('provider', $providers, ['value' => $row['provider'] ?? 'dry']);
        echo '</div>';

        echo "<div class='mb-3'><label class='form-label'>Modèle</label>";
        echo "<input class='form-control' type='text' name='model' value='" . Html::entities_deep($row['model'] ?? '') . "' placeholder='gpt-4o-mini, claude-haiku-4-5, qwen2.5:14b…'></div>";

        echo "<div class='mb-3'><label class='form-label'>Adresse (Ollama seulement)</label>";
        echo "<input class='form-control' type='text' name='endpoint' value='" . Html::entities_deep($row['endpoint'] ?? '') . "' placeholder='http://ollama.local:11434'></div>";

        echo "<div class='mb-3'><label class='form-label'>Clé API — $keyHint</label>";
        echo "<input class='form-control' type='password' name='api_key' value='' autocomplete='new-password' placeholder='laisser vide pour ne pas changer'>";
        echo "<div class='form-text'>Stockée chiffrée avec le mécanisme natif de GLPI. Jamais affichée.</div></div>";

        echo "<div class='mb-3'><label class='form-label'>Langue des relances</label>";
        \Dropdown::showFromArray('language', ['fr' => 'Français', 'en' => 'English'], ['value' => $row['language'] ?? 'fr']);
        echo '</div>';

        echo "<div class='mb-3'><label class='form-label'>Catégories jamais traitées</label>";
        \ITILCategory::dropdown(['name' => 'excluded_categories[]', 'multiple' => true, 'values' => array_filter(array_map('intval', explode(',', (string) ($row['excluded_categories'] ?? ''))))]);
        echo "<div class='form-text'>RH, paie, médical, accès : les tickets de ces catégories ne sont jamais envoyés au modèle.</div></div>";

        echo "<button type='submit' name='update' class='btn btn-primary'>Enregistrer</button> ";
        if ($entities_id > 0 && !$herite) {
            echo "<button type='submit' name='heriter' class='btn btn-outline-danger'>Revenir à la configuration parente</button> ";
        }
        if ($entities_id === 0) {
            echo "<a class='btn btn-outline-secondary' href='" . \Plugin::getWebDir('glpiassist') . "/front/diagnostic.php'>Diagnostic</a>";
        }
        echo '</div></div>';
        Html::closeForm();
        return true;
    }

    /**
     * Utilisateur technique auteur des suivis. Profil Technicien, entité racine, récursif.
     */
    public static function ensureTechnicalUser(): int
    {
        $user = new User();
        if ($user->getFromDBbyName(self::TECH_USER_LOGIN)) {
            if (!$user->fields['is_active']) {
                $user->update(['id' => $user->getID(), 'is_active' => 1]);
            }
            return $user->getID();
        }

        $profile = new Profile();
        $profiles_id = 0;
        foreach ($profile->find(['interface' => 'central'], ['id'], 1) as $p) {
            $profiles_id = (int) $p['id'];
        }
        foreach ($profile->find(['name' => 'Technician']) as $p) {
            $profiles_id = (int) $p['id'];
        }

        $id = (int) $user->add([
            'name'          => self::TECH_USER_LOGIN,
            'realname'      => 'GLPI Assist',
            'is_active'     => 1,
            'authtype'      => Auth::DB_GLPI,
            'password'      => bin2hex(random_bytes(24)),
            '_profiles_id'  => $profiles_id,
            '_entities_id'  => 0,
            '_is_recursive' => 1,
        ]);

        if ($id > 0 && $profiles_id > 0 && !countElementsInTable(Profile_User::getTable(), ['users_id' => $id])) {
            (new Profile_User())->add(['users_id' => $id, 'profiles_id' => $profiles_id, 'entities_id' => 0, 'is_recursive' => 1]);
        }
        return $id;
    }

    public static function disableTechnicalUser(): void
    {
        $user = new User();
        if ($user->getFromDBbyName(self::TECH_USER_LOGIN)) {
            $user->update(['id' => $user->getID(), 'is_active' => 0]);
        }
    }

    public static function technicalUserId(): int
    {
        $user = new User();
        return $user->getFromDBbyName(self::TECH_USER_LOGIN) ? $user->getID() : 0;
    }
}
