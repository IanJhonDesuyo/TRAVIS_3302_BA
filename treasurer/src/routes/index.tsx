import { createFileRoute } from "@tanstack/react-router";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "TRAVIS · Treasurer Dashboard" },
      { name: "description", content: "Treasurer Dashboard for the TRAVIS Traffic Violation Recognition and AI Surveillance System." },
      { property: "og:title", content: "TRAVIS · Treasurer Dashboard" },
      { property: "og:description", content: "Manage traffic violation payments, collections, and reports." },
    ],
  }),
  component: Index,
});

function Index() {
  return (
    <iframe
      src="/travis/index.html"
      title="TRAVIS Treasurer Dashboard"
      style={{ border: 0, width: "100vw", height: "100vh", display: "block" }}
    />
  );
}
