package api

import "fmt"

// PayloadVersion mirrors shared/version.js — keep them in lockstep.
const PayloadVersion = 2

func Describe(kind string, v int) string {
	if v != PayloadVersion {
		return fmt.Sprintf("rejected %s: unsupported v%d", kind, v)
	}
	return fmt.Sprintf("accepted %s v%d", kind, v)
}
