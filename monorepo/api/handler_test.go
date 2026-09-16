package api

import "testing"

func TestDescribe(t *testing.T) {
	if got := Describe("click", 2); got != "accepted click v2" {
		t.Fatalf("got %q", got)
	}
	if got := Describe("click", 1); got != "rejected click: unsupported v1" {
		t.Fatalf("got %q", got)
	}
}
