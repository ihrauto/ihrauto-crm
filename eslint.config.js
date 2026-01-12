import js from "@eslint/js";
import prettierRecommended from "eslint-plugin-prettier/recommended";

export default [
    js.configs.recommended,
    prettierRecommended,
    {
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: "module",
            globals: {
                Alpine: "readonly",
                axios: "readonly",
                Echo: "readonly",
                Pusher: "readonly",
                window: "readonly",
                document: "readonly",
                console: "readonly",
                setTimeout: "readonly"
            }
        },
        rules: {
            "no-unused-vars": "warn",
            "no-undef": "warn"
        }
    }
];
