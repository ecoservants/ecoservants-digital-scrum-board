(function () {
  const { createElement: el, useState, useEffect } = wp.element;
  const { Button, TextControl, SelectControl, Spinner } = wp.components;
  const apiFetch = wp.apiFetch;

  function App() {
    const [rows, setRows] = useState([]);
    const [count, setCount] = useState(0);
    const [loading, setLoading] = useState(false);

    const [q, setQ] = useState("");
    const [actionType, setActionType] = useState("");
    const [entityType, setEntityType] = useState("");
    const [userId, setUserId] = useState("");

    const [limit] = useState(50);
    const [offset, setOffset] = useState(0);

    function buildQS() {
      const p = new URLSearchParams();
      p.set("limit", String(limit));
      p.set("offset", String(offset));
      if (q) p.set("q", q);
      if (actionType) p.set("action_type", actionType);
      if (entityType) p.set("entity_type", entityType);
      if (userId) p.set("user_id", userId);
      return p.toString();
    }

    async function load() {
      setLoading(true);
      const qs = buildQS();
      const data = await apiFetch({ path: `/es-scrum/v1/activity?${qs}` });
      setRows(data.rows || []);
      setCount(data.count || 0);
      setLoading(false);
    }

    useEffect(() => { load(); }, [offset]);

    function apply() { setOffset(0); load(); }
    function exportCSV() {
      const qs = buildQS();
      window.location.href = `${window.location.origin}/wp-json/es-scrum/v1/activity/export?${qs}`;
    }

    return el("div", {},
      el("div", { style: { display: "flex", gap: 12, flexWrap: "wrap", marginBottom: 12 } },
        el(TextControl, { label: "Search", value: q, onChange: setQ }),
        el(TextControl, { label: "User ID", value: userId, onChange: setUserId }),
        el(TextControl, { label: "Action", value: actionType, onChange: setActionType, placeholder: "task.update" }),
        el(SelectControl, {
          label: "Entity",
          value: entityType,
          onChange: setEntityType,
          options: [
            { label: "All", value: "" },
            { label: "Task", value: "task" },
            { label: "Sprint", value: "sprint" },
            { label: "Comment", value: "comment" },
            { label: "Attachment", value: "attachment" },
          ]
        }),
        el(Button, { variant: "primary", onClick: apply, style: { marginTop: 22 } }, "Apply"),
        el(Button, { variant: "secondary", onClick: exportCSV, style: { marginTop: 22 } }, "Export CSV")
      ),

      loading ? el(Spinner) : null,

      el("table", { className: "widefat striped" },
        el("thead", {},
          el("tr", {},
            el("th", {}, "Time"),
            el("th", {}, "User"),
            el("th", {}, "Action"),
            el("th", {}, "Entity"),
            el("th", {}, "Metadata")
          )
        ),
        el("tbody", {},
          rows.map(r => el("tr", { key: r.id },
            el("td", {}, r.created_at),
            el("td", {}, r.user_id || "System"),
            el("td", {}, r.action_type),
            el("td", {}, `${r.entity_type} #${r.entity_id || ""}`),
            el("td", {}, (r.metadata || "").slice(0, 160))
          ))
        )
      ),

      el("div", { style: { marginTop: 12, display: "flex", justifyContent: "space-between" } },
        el(Button, { disabled: offset <= 0, onClick: () => setOffset(Math.max(0, offset - limit)) }, "Prev"),
        el("div", {}, `Showing ${rows.length} of ${count}`),
        el(Button, { disabled: offset + limit >= count, onClick: () => setOffset(offset + limit) }, "Next")
      )
    );
  }

  document.addEventListener("DOMContentLoaded", () => {
    const root = document.getElementById("es-scrum-activity-admin");
    if (root) wp.element.render(el(App), root);
  });
})();
