// Minimal stub for the `@anthropic-ai/sdk` package, used only by
// tests/security/admin-jwt-replay.test.js so that requiring `question-api.js`
// (which transitively pulls in providers/anthropic.js) does not crash on
// missing optional dependencies. The middleware under test never calls into
// any provider, so a no-op constructor is enough.
class Anthropic {
  constructor(opts) {
    this.opts = opts || {};
    this.messages = { create: async () => ({ content: [] }) };
  }
}
module.exports = Anthropic;
module.exports.default = Anthropic;
